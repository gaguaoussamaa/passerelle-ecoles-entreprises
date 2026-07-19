<?php

namespace Tests\Feature;

use App\Mail\InvitationMail;
use App\Mail\MissionMail;
use App\Models\Compte;
use App\Models\Declaration;
use App\Models\Entreprise;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Mission;
use App\Models\Promotion;
use App\Models\Responsable;
use App\Models\TuteurPedagogique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Chemin B : déclarations hors plateforme (RG-27/46/47 — TV-15/16), désignation
 * des tuteurs (RG-28 — TV-18), relance d'invitation et annulation (RG-30 — TV-17).
 */
class DeclarationsTest extends TestCase
{
    use RefreshDatabase;

    private Compte $respA;
    private Compte $respB;
    private Compte $entreprise;
    private Compte $cible;
    private Etablissement $etab;
    private Formation $formation;
    private Promotion $promo;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->etab = Etablissement::create([
            'nom' => 'École A', 'siret' => '00000000000001', 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $this->respA = Compte::create(['email' => 'resp.a@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $this->respA->id, 'etablissement_id' => $this->etab->id, 'nom' => 'R', 'prenom' => 'A']);
        $this->formation = Formation::create(['etablissement_id' => $this->etab->id, 'intitule' => 'M2', 'niveau' => 'Bac+5', 'type_mission' => 'alternance']);
        $this->promo = Promotion::create(['formation_id' => $this->formation->id, 'libelle' => 'Promo A', 'annee_universitaire' => '2026-2027']);

        $etabB = Etablissement::create([
            'nom' => 'École B', 'siret' => '00000000000002', 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $this->respB = Compte::create(['email' => 'resp.b@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $this->respB->id, 'etablissement_id' => $etabB->id, 'nom' => 'R', 'prenom' => 'B']);

        $this->entreprise = Compte::create(['email' => 'ent@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $this->entreprise->id, 'raison_sociale' => 'TestCorp']);

        $this->cible = Compte::create(['email' => 'etu@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant']);
        Etudiant::create(['compte_id' => $this->cible->id, 'promotion_id' => $this->promo->id, 'nom' => 'E', 'prenom' => 'T', 'statut_scolarite' => 'actif']);
    }

    private function declarer(array $surcharges = [])
    {
        return $this->actingAs($this->cible)->post('/declaration', $surcharges + [
            'type_mission' => 'stage', 'date_debut_prevue' => '2026-09-07', 'date_fin_prevue' => '2027-01-29',
            'entreprise_saisie' => 'Nouvelle SARL', 'contact_nom' => 'Jean Contact',
            'contact_email' => 'contact@nouvelle.demo',
            'description' => 'Stage de développement web au sein d\'une petite équipe.',
        ]);
    }

    /** Mission « en montage » issue d'une déclaration recevable, rattachée à l'entreprise activée. */
    private function missionDeclaree(): Mission
    {
        $declaration = Declaration::create([
            'etudiant_id' => $this->cible->id, 'type_mission' => 'stage', 'statut' => 'recevable',
            'date_debut_prevue' => '2026-09-07', 'date_fin_prevue' => '2027-01-29',
            'entreprise_saisie' => 'TestCorp', 'contact_nom' => 'J', 'contact_email' => 'ent@t.demo',
            'description' => 'Stage de développement web au sein d\'une petite équipe.',
        ]);

        return Mission::create([
            'etudiant_id' => $this->cible->id, 'entreprise_id' => $this->entreprise->id,
            'declaration_id' => $declaration->id, 'type' => 'stage',
            'date_debut' => '2026-09-07', 'date_fin' => '2027-01-29',
        ]);
    }

    public function test_depot_soumise_et_une_seule_declaration_en_cours(): void
    {
        $this->declarer()->assertRedirect();
        $this->assertDatabaseHas('declarations', ['etudiant_id' => $this->cible->id, 'statut' => 'soumise']);

        $this->declarer()->assertSessionHasErrors('declaration');                  // RG-46
        $this->assertDatabaseCount('declarations', 1);
    }

    public function test_abandon_avant_traitement_puis_nouvelle_declaration(): void
    {
        $this->declarer();
        $this->actingAs($this->cible)->post('/declaration/abandonner')->assertRedirect();
        $this->assertDatabaseHas('declarations', ['statut' => 'abandonnee']);      // RG-46

        $this->declarer()->assertRedirect();                                       // libéré
        $this->assertDatabaseCount('declarations', 2);
    }

    public function test_refus_motive_puis_correction_resoumise(): void
    {
        $this->declarer();
        $declaration = Declaration::sole();

        $this->actingAs($this->respA)->post('/ecole/declarations/'.$declaration->id.'/refuser', [])
            ->assertSessionHasErrors('motif_refus');                               // motif obligatoire
        $this->actingAs($this->respA)->post('/ecole/declarations/'.$declaration->id.'/refuser',
            ['motif_refus' => 'Dates incompatibles avec le calendrier.'])->assertRedirect();
        $this->assertSame('refusee', $declaration->fresh()->statut);

        $this->declarer(['date_debut_prevue' => '2026-10-05']);                    // correction (TV-16)
        $declaration->refresh();
        $this->assertSame('soumise', $declaration->statut);                        // même dossier re-soumis
        $this->assertNull($declaration->motif_refus);
        $this->assertSame('2026-10-05', $declaration->date_debut_prevue->format('Y-m-d'));
        $this->assertDatabaseCount('declarations', 1);
    }

    public function test_validation_avec_entreprise_inconnue_invite_et_cree_la_mission(): void
    {
        $this->declarer();
        $declaration = Declaration::sole();

        $this->actingAs($this->respA)->post('/ecole/declarations/'.$declaration->id.'/valider')->assertRedirect();

        $this->assertSame('recevable', $declaration->fresh()->statut);             // TV-15
        $compte = Compte::where('email', 'contact@nouvelle.demo')->sole();         // RG-27 : compte invité
        $this->assertSame('entreprise', $compte->role);
        $this->assertNull($compte->mot_de_passe);
        $this->assertDatabaseHas('entreprises', ['compte_id' => $compte->id, 'raison_sociale' => 'Nouvelle SARL']);
        $this->assertDatabaseHas('partenariats', [
            'etablissement_id' => $this->etab->id, 'entreprise_id' => $compte->id, 'statut' => 'en_attente',
        ]);
        $mission = Mission::sole();
        $this->assertSame($declaration->id, $mission->declaration_id);             // origine chemin B (RG-26)
        $this->assertNull($mission->candidature_id);
        $this->assertSame('en_montage', $mission->statut);
        Mail::assertSent(InvitationMail::class, fn ($m) => $m->hasTo('contact@nouvelle.demo'));
    }

    public function test_validation_avec_entreprise_connue_notifie_sans_recreer(): void
    {
        $this->declarer(['contact_email' => 'ent@t.demo', 'entreprise_saisie' => 'TestCorp']);
        $declaration = Declaration::sole();
        $comptesAvant = Compte::count();

        $this->actingAs($this->respA)->post('/ecole/declarations/'.$declaration->id.'/valider')->assertRedirect();

        $this->assertSame($comptesAvant, Compte::count());                         // pas de doublon
        $this->assertDatabaseHas('partenariats', [
            'etablissement_id' => $this->etab->id, 'entreprise_id' => $this->entreprise->id, 'statut' => 'actif',
        ]);
        $this->assertSame('en_montage', Mission::sole()->statut);
        Mail::assertSent(MissionMail::class, fn ($m) => $m->hasTo('ent@t.demo') && $m->evenement === 'entreprise_raccordee');
        Mail::assertNotSent(InvitationMail::class);
    }

    public function test_contact_non_entreprise_bloque_la_validation(): void
    {
        $this->declarer(['contact_email' => 'resp.a@t.demo']);                     // e-mail d'un responsable
        $declaration = Declaration::sole();

        $this->actingAs($this->respA)->post('/ecole/declarations/'.$declaration->id.'/valider')
            ->assertSessionHasErrors('valider');
        $this->assertSame('soumise', $declaration->fresh()->statut);
        $this->assertDatabaseCount('missions', 0);
    }

    public function test_une_ecole_ne_traite_pas_les_declarations_d_une_autre(): void
    {
        $this->declarer();
        $declaration = Declaration::sole();

        $this->actingAs($this->respB)->post('/ecole/declarations/'.$declaration->id.'/valider')->assertNotFound();
        $this->actingAs($this->respB)->post('/ecole/declarations/'.$declaration->id.'/refuser',
            ['motif_refus' => 'xxxxx'])->assertNotFound();                         // TI-02
    }

    public function test_designation_des_tuteurs_fait_passer_en_contractualisation(): void
    {
        $mission = $this->missionDeclaree();

        $horsFormation = Compte::create(['email' => 'tut.hors@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'tuteur_pedagogique']);
        TuteurPedagogique::create(['compte_id' => $horsFormation->id, 'etablissement_id' => $this->etab->id, 'nom' => 'H', 'prenom' => 'F', 'actif' => true]);
        $this->actingAs($this->respA)->post('/ecole/missions/'.$mission->id.'/tuteur', ['tuteur_id' => $horsFormation->id])
            ->assertSessionHasErrors('tuteur');                                    // RG-28 : non rattaché

        $rattache = Compte::create(['email' => 'tut.ok@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'tuteur_pedagogique']);
        $tuteur = TuteurPedagogique::create(['compte_id' => $rattache->id, 'etablissement_id' => $this->etab->id, 'nom' => 'O', 'prenom' => 'K', 'actif' => true]);
        $tuteur->formations()->attach($this->formation->id);
        $this->actingAs($this->respA)->post('/ecole/missions/'.$mission->id.'/tuteur', ['tuteur_id' => $rattache->id])
            ->assertRedirect();
        $this->assertSame('en_montage', $mission->fresh()->statut);                // un seul tuteur : pas encore

        $this->actingAs($this->entreprise)->post('/entreprise/missions/'.$mission->id.'/tuteur',
            ['nom' => 'Durand', 'prenom' => 'Paul', 'email' => 'p.durand@testcorp.demo'])->assertRedirect();

        $mission->refresh();
        $this->assertSame('en_contractualisation', $mission->statut);              // RG-28 / TV-18
        $this->assertSame($rattache->id, $mission->tuteur_pedagogique_id);
        $this->assertDatabaseHas('tuteurs_entreprise', ['entreprise_id' => $this->entreprise->id, 'nom' => 'Durand']);
    }

    public function test_une_entreprise_ne_designe_pas_sur_la_mission_d_une_autre(): void
    {
        $mission = $this->missionDeclaree();

        $autre = Compte::create(['email' => 'ent2@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $autre->id, 'raison_sociale' => 'AutreCorp']);

        $this->actingAs($autre)->post('/entreprise/missions/'.$mission->id.'/tuteur',
            ['nom' => 'X', 'prenom' => 'Y'])->assertNotFound();
    }

    public function test_relance_d_une_entreprise_jamais_activee(): void
    {
        $this->declarer();
        $this->actingAs($this->respA)->post('/ecole/declarations/'.Declaration::sole()->id.'/valider');
        $mission = Mission::sole();

        $this->actingAs($this->respA)->post('/ecole/missions/'.$mission->id.'/invitation')->assertRedirect();

        $compte = Compte::where('email', 'contact@nouvelle.demo')->sole();
        $this->assertSame(2, $compte->invitations()->count());                     // TV-17
        $this->assertSame(1, $compte->invitations()->where('statut', 'invalidee')->count());  // RG-02
        Mail::assertSent(InvitationMail::class, 2);

        $this->actingAs($this->respB)->post('/ecole/missions/'.$mission->id.'/invitation')->assertNotFound();
    }

    public function test_annulation_motivee_libere_l_etudiant(): void
    {
        $mission = $this->missionDeclaree();

        $this->declarer()->assertSessionHasErrors('declaration');                  // mission en cours : bloqué

        $this->actingAs($this->respA)->post('/ecole/missions/'.$mission->id.'/annuler', [
            'motif_arret' => 'Entreprise finalement indisponible.', 'date_effet_arret' => '2026-08-01',
        ])->assertRedirect();

        $mission->refresh();
        $this->assertSame('annulee', $mission->statut);                            // RG-30
        $this->assertSame('2026-08-01', $mission->date_effet_arret->format('Y-m-d'));
        Mail::assertSent(MissionMail::class, fn ($m) => $m->hasTo('etu@t.demo') && $m->evenement === 'annulee');

        $this->declarer()->assertRedirect();                                       // étudiant libéré (TV-17)
        $this->assertDatabaseHas('declarations', ['statut' => 'soumise']);
    }
}
