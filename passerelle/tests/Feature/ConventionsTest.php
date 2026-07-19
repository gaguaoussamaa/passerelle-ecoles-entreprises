<?php

namespace Tests\Feature;

use App\Models\Compte;
use App\Models\Declaration;
use App\Models\Entreprise;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Mission;
use App\Models\Promotion;
use App\Models\Responsable;
use App\Models\TuteurEntreprise;
use App\Models\TuteurPedagogique;
use App\Models\VersionConvention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Module conventions : complétude bloquante (RG-31), versions immuables PDF +
 * SHA-256 (RG-32/45), circuit séquentiel (RG-33), refus → nouvelle version
 * (RG-34), approbations libres tracées (RG-35), contractualisation + échéancier
 * (RG-36/38), annulation-remplacement (RG-37) — TV-19..22.
 */
class ConventionsTest extends TestCase
{
    use RefreshDatabase;

    private Compte $respA;
    private Compte $respB;
    private Compte $entreprise;
    private Compte $etudiant;
    private Compte $tuteur;
    private Etablissement $etab;
    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->etab = Etablissement::create([
            'nom' => 'École A', 'siret' => '00000000000001', 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $this->respA = Compte::create(['email' => 'resp.a@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $this->respA->id, 'etablissement_id' => $this->etab->id, 'nom' => 'R', 'prenom' => 'A']);
        $formation = Formation::create(['etablissement_id' => $this->etab->id, 'intitule' => 'M2', 'niveau' => 'Bac+5', 'type_mission' => 'alternance']);
        $promo = Promotion::create(['formation_id' => $formation->id, 'libelle' => 'Promo A', 'annee_universitaire' => '2026-2027']);

        $etabB = Etablissement::create([
            'nom' => 'École B', 'siret' => '00000000000002', 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $this->respB = Compte::create(['email' => 'resp.b@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $this->respB->id, 'etablissement_id' => $etabB->id, 'nom' => 'R', 'prenom' => 'B']);

        $this->entreprise = Compte::create(['email' => 'ent@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $this->entreprise->id, 'raison_sociale' => 'TestCorp', 'siret' => '99999999900099', 'ville' => 'Lille']);

        $this->etudiant = Compte::create(['email' => 'etu@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant']);
        Etudiant::create(['compte_id' => $this->etudiant->id, 'promotion_id' => $promo->id, 'nom' => 'E', 'prenom' => 'T', 'statut_scolarite' => 'actif']);

        $this->tuteur = Compte::create(['email' => 'tut@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'tuteur_pedagogique']);
        $profilTuteur = TuteurPedagogique::create(['compte_id' => $this->tuteur->id, 'etablissement_id' => $this->etab->id, 'nom' => 'T', 'prenom' => 'P', 'actif' => true]);
        $profilTuteur->formations()->attach($formation->id);

        $declaration = Declaration::create([
            'etudiant_id' => $this->etudiant->id, 'type_mission' => 'stage', 'statut' => 'recevable',
            'date_debut_prevue' => '2026-09-07', 'date_fin_prevue' => '2027-01-29',
            'entreprise_saisie' => 'TestCorp', 'contact_nom' => 'J', 'contact_email' => 'ent@t.demo',
            'description' => 'Stage de développement web au sein d\'une petite équipe.',
        ]);
        $tuteurEntreprise = TuteurEntreprise::create(['entreprise_id' => $this->entreprise->id, 'nom' => 'D', 'prenom' => 'E']);
        $this->mission = Mission::create([
            'etudiant_id' => $this->etudiant->id, 'entreprise_id' => $this->entreprise->id,
            'declaration_id' => $declaration->id, 'tuteur_pedagogique_id' => $this->tuteur->id,
            'tuteur_entreprise_id' => $tuteurEntreprise->id, 'type' => 'stage',
            'date_debut' => '2026-09-07', 'date_fin' => '2027-01-29', 'statut' => 'en_contractualisation',
        ]);
    }

    private function generer()
    {
        return $this->actingAs($this->respA)->post('/ecole/missions/'.$this->mission->id.'/convention');
    }

    private function version(): VersionConvention
    {
        return VersionConvention::latest('id')->firstOrFail();
    }

    /** Déroule les quatre validations dans l'ordre (RG-33). */
    private function circuitComplet(VersionConvention $version): void
    {
        foreach ([$this->etudiant, $this->entreprise, $this->tuteur, $this->respA] as $partie) {
            $this->actingAs($partie)->post('/conventions/'.$version->id.'/valider')->assertSessionHasNoErrors();
        }
    }

    /** Convention approuvée par les quatre parties. */
    private function conventionApprouvee(): VersionConvention
    {
        $this->generer();
        $version = $this->version();
        $this->circuitComplet($version);
        foreach ([$this->respA, $this->etudiant, $this->entreprise, $this->tuteur] as $partie) {
            $this->actingAs($partie)->post('/conventions/'.$version->id.'/approuver')->assertSessionHasNoErrors();
        }

        return $version->fresh();
    }

    public function test_generation_bloquee_par_les_manques_puis_possible(): void
    {
        $this->entreprise->entreprise->update(['siret' => null, 'ville' => null]);

        $reponse = $this->generer();
        $reponse->assertSessionHasErrors('convention');                            // RG-31
        $this->assertStringContainsString('SIRET', session('errors')->first('convention'));
        $this->assertDatabaseCount('versions_convention', 0);

        $this->entreprise->entreprise->update(['siret' => '99999999900099', 'ville' => 'Lille']);
        $this->generer()->assertSessionHasNoErrors();

        $version = $this->version();                                               // RG-32 / RG-45
        $this->assertSame(1, $version->numero);
        $this->assertSame('emise', $version->statut);
        Storage::assertExists($version->fichier_pdf);
        $pdf = Storage::get($version->fichier_pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(hash('sha256', $pdf), $version->empreinte);              // empreinte fidèle
    }

    public function test_le_circuit_de_validation_est_sequentiel(): void
    {
        $this->generer();
        $version = $this->version();

        $this->actingAs($this->entreprise)->post('/conventions/'.$version->id.'/valider')
            ->assertSessionHasErrors('convention');                                // pas son tour (RG-33)

        $this->actingAs($this->etudiant)->post('/conventions/'.$version->id.'/valider')->assertSessionHasNoErrors();
        $this->assertSame('en_validation', $version->fresh()->statut);

        $this->actingAs($this->tuteur)->post('/conventions/'.$version->id.'/valider')
            ->assertSessionHasErrors('convention');                                // l'entreprise d'abord

        $this->actingAs($this->entreprise)->post('/conventions/'.$version->id.'/valider')->assertSessionHasNoErrors();
        $this->actingAs($this->tuteur)->post('/conventions/'.$version->id.'/valider')->assertSessionHasNoErrors();
        $this->actingAs($this->respA)->post('/conventions/'.$version->id.'/valider')->assertSessionHasNoErrors();

        $this->assertSame('validee', $version->fresh()->statut);
        $this->assertSame(4, $version->actions()->where('type', 'validation')->distinct()->count('compte_id'));  // auteurs réels
    }

    public function test_refus_motive_et_reprise_du_circuit_sur_nouvelle_version(): void
    {
        $this->generer();
        $v1 = $this->version();
        $this->actingAs($this->etudiant)->post('/conventions/'.$v1->id.'/valider');

        $this->actingAs($this->entreprise)->post('/conventions/'.$v1->id.'/refuser', [])
            ->assertSessionHasErrors('motif');                                     // motif obligatoire (RG-34)
        $this->actingAs($this->entreprise)->post('/conventions/'.$v1->id.'/refuser',
            ['motif' => 'Dates de mission erronées.'])->assertSessionHasNoErrors();
        $this->assertSame('refusee_correction', $v1->fresh()->statut);

        $this->generer()->assertSessionHasNoErrors();                              // correction → v2
        $v2 = $this->version();
        $this->assertSame(2, $v2->numero);
        $this->assertSame('remplacee', $v1->fresh()->statut);                      // RG-32 / TV-20

        $this->actingAs($this->entreprise)->post('/conventions/'.$v2->id.'/valider')
            ->assertSessionHasErrors('convention');                                // circuit repris du début
        $this->actingAs($this->etudiant)->post('/conventions/'.$v2->id.'/valider')->assertSessionHasNoErrors();
    }

    public function test_une_seule_version_en_circulation(): void
    {
        $this->generer()->assertSessionHasNoErrors();
        $this->generer()->assertSessionHasErrors('convention');
        $this->assertDatabaseCount('versions_convention', 1);
    }

    public function test_approbations_libres_contractualisation_et_echeancier(): void
    {
        $this->generer();
        $version = $this->version();
        $this->circuitComplet($version);
        $this->assertSame('validee', $version->fresh()->statut);

        // ordre volontairement quelconque (RG-35)
        $this->actingAs($this->respA)->post('/conventions/'.$version->id.'/approuver')->assertSessionHasNoErrors();
        $this->actingAs($this->respA)->post('/conventions/'.$version->id.'/approuver')
            ->assertSessionHasErrors('convention');                                // pas deux fois
        $this->actingAs($this->etudiant)->post('/conventions/'.$version->id.'/approuver');
        $this->actingAs($this->entreprise)->post('/conventions/'.$version->id.'/approuver');
        $this->assertSame('en_approbation', $version->fresh()->statut);

        $this->actingAs($this->tuteur)->post('/conventions/'.$version->id.'/approuver');   // 4e

        $this->assertSame('approuvee', $version->fresh()->statut);
        $this->assertSame('contractualisee', $this->mission->fresh()->statut);     // RG-36
        $this->assertDatabaseHas('journal_audit', [                                // RG-35 : empreinte consignée
            'action' => 'convention_approuvee_tuteur_pedagogique', 'objet_id' => $version->id,
            'compte_id' => $this->tuteur->id, 'details' => 'empreinte '.$version->empreinte,
        ]);
        // RG-38 : mission du 07/09 au 29/01 → 4 rapports mensuels + 1 mi-parcours
        $this->assertSame(4, $this->mission->jalons()->where('type', 'rapport_mensuel')->count());
        $this->assertSame(1, $this->mission->jalons()->where('type', 'mi_parcours')->count());
    }

    public function test_convention_approuvee_intangible_puis_annulation_remplacement(): void
    {
        $version = $this->conventionApprouvee();

        $this->actingAs($this->etudiant)->post('/conventions/'.$version->id.'/valider')
            ->assertSessionHasErrors('convention');                                // intangible (RG-37)
        $this->actingAs($this->etudiant)->post('/conventions/'.$version->id.'/annuler',
            ['motif' => 'xxxxx'])->assertSessionHasErrors('convention');           // réservé au responsable

        $this->actingAs($this->respA)->post('/conventions/'.$version->id.'/annuler',
            ['motif' => 'Changement de dates de la mission.'])->assertSessionHasNoErrors();

        $this->assertSame('annulee', $version->fresh()->statut);                   // consultable, archivée
        $this->assertSame('en_contractualisation', $this->mission->fresh()->statut);
        $this->assertSame(0, $this->mission->jalons()->count());                   // échéancier arrêté

        $this->generer()->assertSessionHasNoErrors();                              // remplacement (TV-22)
        $v2 = $this->version();
        $this->assertSame(2, $v2->numero);
        $this->assertSame('emise', $v2->statut);                                   // circuit complet à refaire
    }

    public function test_cloisonnement_des_conventions(): void
    {
        $this->generer();
        $version = $this->version();

        $autreEtudiant = Compte::create(['email' => 'etu2@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant']);
        Etudiant::create(['compte_id' => $autreEtudiant->id, 'promotion_id' => $this->mission->etudiant->promotion_id, 'nom' => 'X', 'prenom' => 'Y', 'statut_scolarite' => 'actif']);
        $autreEntreprise = Compte::create(['email' => 'ent2@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $autreEntreprise->id, 'raison_sociale' => 'AutreCorp']);

        $this->actingAs($autreEtudiant)->get('/conventions/'.$version->id.'/pdf')->assertNotFound();
        $this->actingAs($autreEntreprise)->post('/conventions/'.$version->id.'/valider')->assertNotFound();
        $this->actingAs($this->respB)->post('/conventions/'.$version->id.'/annuler', ['motif' => 'xxxxx'])->assertNotFound();
        $this->actingAs($this->respB)->post('/ecole/missions/'.$this->mission->id.'/convention')->assertNotFound();
    }

    public function test_le_pdf_est_telechargeable_par_les_quatre_parties(): void
    {
        $this->generer();
        $version = $this->version();

        foreach ([$this->etudiant, $this->entreprise, $this->tuteur, $this->respA] as $partie) {
            $this->actingAs($partie)->get('/conventions/'.$version->id.'/pdf')->assertOk();
        }
    }
}
