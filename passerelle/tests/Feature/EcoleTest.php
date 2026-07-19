<?php

namespace Tests\Feature;

use App\Mail\InvitationMail;
use App\Models\Compte;
use App\Models\Domaine;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\JournalAudit;
use App\Models\Promotion;
use App\Models\Responsable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Module structure école : cloisonnement (TI-02 partiel), import CSV (RG-13),
 * dossiers étudiants (EF-33, RG-15), invitations (RG-02).
 */
class EcoleTest extends TestCase
{
    use RefreshDatabase;

    private Compte $respA;
    private Compte $respB;
    private Etablissement $etabA;
    private Etablissement $etabB;
    private Promotion $promoA;
    private Formation $formationA;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        [$this->etabA, $this->respA] = $this->ecole('École A', 'resp.a@test.demo', '00000000000001');
        [$this->etabB, $this->respB] = $this->ecole('École B', 'resp.b@test.demo', '00000000000002');

        $this->formationA = Formation::create([
            'etablissement_id' => $this->etabA->id, 'intitule' => 'M2 Dev',
            'niveau' => 'Bac+5', 'type_mission' => 'alternance',
        ]);
        $this->promoA = Promotion::create([
            'formation_id' => $this->formationA->id, 'libelle' => 'M2 Dev 2026-2027',
            'annee_universitaire' => '2026-2027',
        ]);
    }

    private function ecole(string $nom, string $email, string $siret): array
    {
        $etab = Etablissement::create([
            'nom' => $nom, 'siret' => $siret, 'ville' => 'Test',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $compte = Compte::create(['email' => $email, 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $compte->id, 'etablissement_id' => $etab->id, 'nom' => 'R', 'prenom' => 'T']);

        return [$etab, $compte];
    }

    private function etudiantA(string $email = 'etu.a@test.demo', string $statut = 'invite'): Etudiant
    {
        $compte = Compte::create(['email' => $email, 'role' => 'etudiant']);

        return Etudiant::create([
            'compte_id' => $compte->id, 'promotion_id' => $this->promoA->id,
            'nom' => 'Etu', 'prenom' => 'A', 'statut_scolarite' => $statut,
        ]);
    }

    // ------------------------------------------------------ Cloisonnement
    public function test_un_responsable_ne_voit_pas_les_ressources_d_une_autre_ecole(): void
    {
        $etudiant = $this->etudiantA();

        $this->actingAs($this->respB)->get('/ecole/etudiants/'.$etudiant->compte_id)->assertNotFound();     // TI-02
        $this->actingAs($this->respB)->post('/ecole/formations/'.$this->formationA->id.'/archiver')->assertNotFound();
        $this->actingAs($this->respB)->post('/ecole/etudiants/'.$etudiant->compte_id.'/statut', ['statut' => 'sorti'])->assertNotFound();

        $this->assertSame('invite', $etudiant->fresh()->statut_scolarite, 'Aucune modification inter-écoles.');
    }

    public function test_l_espace_ecole_est_reserve_au_role_responsable(): void
    {
        $entreprise = Compte::create(['email' => 'ent@test.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        \App\Models\Entreprise::create(['compte_id' => $entreprise->id, 'raison_sociale' => 'X']);

        $this->actingAs($entreprise)->get('/ecole/formations')->assertForbidden();      // RG-06
    }

    public function test_la_liste_des_etudiants_est_limitee_a_l_etablissement(): void
    {
        $this->etudiantA();

        $this->actingAs($this->respB)->get('/ecole/etudiants')->assertOk()->assertDontSee('etu.a@test.demo');
        $this->actingAs($this->respA)->get('/ecole/etudiants')->assertOk()->assertSee('etu.a@test.demo');
    }

    // ------------------------------------------------------------- Gestion
    public function test_creation_d_une_formation_avec_domaines(): void
    {
        $domaine = Domaine::create(['code' => 'INFO', 'libelle' => 'Informatique']);

        $this->actingAs($this->respA)->post('/ecole/formations', [
            'intitule' => 'BTS SIO', 'niveau' => 'Bac+2', 'type_mission' => 'stage',
            'domaines' => [$domaine->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('formations', ['intitule' => 'BTS SIO', 'etablissement_id' => $this->etabA->id]);
    }

    public function test_creation_manuelle_d_un_etudiant_envoie_l_invitation(): void
    {
        $this->actingAs($this->respA)->post('/ecole/etudiants', [
            'nom' => 'Nouveau', 'prenom' => 'Étu', 'email' => 'nouveau@test.demo',
            'promotion_id' => $this->promoA->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('etudiants', ['nom' => 'Nouveau', 'statut_scolarite' => 'invite']);
        Mail::assertSent(InvitationMail::class, fn ($m) => $m->hasTo('nouveau@test.demo'));
    }

    // ---------------------------------------------------- Import CSV RG-13
    public function test_import_csv_cree_les_lignes_valides_et_rejette_les_invalides_avec_motif(): void
    {
        Compte::create(['email' => 'deja.pris@test.demo', 'role' => 'etudiant']);   // doublon préparé

        $csv = "nom;prenom;email;promotion\n"
            ."Kaddouri;Sarah;sarah@test.demo;M2 Dev 2026-2027\n"
            ."Benali;Mehdi;mehdi@test.demo;M2 Dev 2026-2027\n"
            ."Robert;Anne;adresse-invalide;M2 Dev 2026-2027\n"
            ."Simon;Paul;deja.pris@test.demo;M2 Dev 2026-2027\n"
            ."Moreau;Léa;lea@test.demo;Promo Inconnue\n";

        $reponse = $this->actingAs($this->respA)->post('/ecole/etudiants/import', [
            'fichier' => UploadedFile::fake()->createWithContent('etudiants.csv', $csv),
        ]);

        $reponse->assertRedirect(route('ecole.etudiants.import'));
        $rapport = session('rapport');

        $this->assertSame(2, $rapport['crees']);
        $this->assertSame(3, $rapport['rejets']);
        $motifs = collect($rapport['lignes'])->pluck('motif')->join(' | ');
        $this->assertStringContainsString('invalide', $motifs);
        $this->assertStringContainsString('déjà utilisée', $motifs);
        $this->assertStringContainsString('inconnue', $motifs);

        $this->assertDatabaseHas('comptes', ['email' => 'sarah@test.demo', 'role' => 'etudiant']);
        Mail::assertSent(InvitationMail::class, 2);
    }

    // ------------------------------------------------- EF-33, RG-02, RG-15
    public function test_le_changement_de_statut_est_applique_reversible_et_trace(): void
    {
        $etudiant = $this->etudiantA(statut: 'actif');

        $this->actingAs($this->respA)->post('/ecole/etudiants/'.$etudiant->compte_id.'/statut', ['statut' => 'sorti'])->assertRedirect();
        $this->assertSame('sorti', $etudiant->fresh()->statut_scolarite);

        $this->actingAs($this->respA)->post('/ecole/etudiants/'.$etudiant->compte_id.'/statut', ['statut' => 'actif'])->assertRedirect();
        $this->assertSame('actif', $etudiant->fresh()->statut_scolarite);                       // réversible

        $this->assertSame(2, JournalAudit::where('action', 'changement_statut_scolarite')
            ->where('objet_id', $etudiant->compte_id)->count());                                // tracé
    }

    public function test_le_renvoi_d_invitation_invalide_l_ancien_jeton(): void
    {
        $etudiant = $this->etudiantA();
        app(\App\Services\InvitationService::class)->inviter($etudiant->compte);

        $this->actingAs($this->respA)->post('/ecole/etudiants/'.$etudiant->compte_id.'/invitation')->assertRedirect();

        $this->assertSame(1, $etudiant->compte->invitations()->where('statut', 'invalidee')->count());  // RG-02
        $this->assertSame(1, $etudiant->compte->invitations()->where('statut', 'active')->count());
    }

    public function test_seule_la_suppression_d_un_dossier_vierge_est_possible(): void
    {
        $vierge = $this->etudiantA('vierge@test.demo');
        $this->actingAs($this->respA)->delete('/ecole/etudiants/'.$vierge->compte_id)->assertRedirect();
        $this->assertDatabaseMissing('comptes', ['email' => 'vierge@test.demo']);               // RG-15 : vierge → supprimé

        $actif = $this->etudiantA('non.vierge@test.demo');
        DB::table('declarations')->insert([
            'etudiant_id' => $actif->compte_id, 'type_mission' => 'stage',
            'date_debut_prevue' => '2026-09-01', 'date_fin_prevue' => '2027-01-31',
            'entreprise_saisie' => 'X', 'contact_nom' => 'Y', 'contact_email' => 'y@x.fr',
            'description' => 'd', 'statut' => 'soumise',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->respA)->delete('/ecole/etudiants/'.$actif->compte_id)->assertSessionHasErrors('suppression');
        $this->assertDatabaseHas('comptes', ['email' => 'non.vierge@test.demo']);               // RG-15 : bloqué
    }
}
