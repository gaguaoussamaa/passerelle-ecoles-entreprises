<?php

namespace Tests\Feature;

use App\Models\Compte;
use App\Models\Declaration;
use App\Models\Entreprise;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Promotion;
use App\Models\Responsable;
use App\Models\Signalement;
use App\Models\TuteurEntreprise;
use App\Models\TuteurPedagogique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Module suivi : états temporels calculés (RG-29), dépôt des rapports et
 * retards (RG-39 — TV-23), signalements (RG-40 — TV-24), interruption d'une
 * mission active vs annulation avant début (RG-30).
 */
class SuiviTest extends TestCase
{
    use RefreshDatabase;

    private Compte $respA;
    private Compte $respB;
    private Compte $entreprise;
    private Compte $etudiant;
    private Compte $tuteur;
    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $etab = Etablissement::create([
            'nom' => 'École A', 'siret' => '00000000000001', 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $this->respA = Compte::create(['email' => 'resp.a@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $this->respA->id, 'etablissement_id' => $etab->id, 'nom' => 'R', 'prenom' => 'A']);
        $formation = Formation::create(['etablissement_id' => $etab->id, 'intitule' => 'M2', 'niveau' => 'Bac+5', 'type_mission' => 'alternance']);
        $promo = Promotion::create(['formation_id' => $formation->id, 'libelle' => 'Promo A', 'annee_universitaire' => '2026-2027']);

        $etabB = Etablissement::create([
            'nom' => 'École B', 'siret' => '00000000000002', 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $this->respB = Compte::create(['email' => 'resp.b@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $this->respB->id, 'etablissement_id' => $etabB->id, 'nom' => 'R', 'prenom' => 'B']);

        $this->entreprise = Compte::create(['email' => 'ent@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $this->entreprise->id, 'raison_sociale' => 'TestCorp']);

        $this->etudiant = Compte::create(['email' => 'etu@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant']);
        Etudiant::create(['compte_id' => $this->etudiant->id, 'promotion_id' => $promo->id, 'nom' => 'E', 'prenom' => 'T', 'statut_scolarite' => 'actif']);

        $this->tuteur = Compte::create(['email' => 'tut@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'tuteur_pedagogique']);
        TuteurPedagogique::create(['compte_id' => $this->tuteur->id, 'etablissement_id' => $etab->id, 'nom' => 'T', 'prenom' => 'P', 'actif' => true]);

        $this->mission = $this->missionContractualisee(now()->subMonths(2), now()->addMonths(2));   // active
    }

    private function missionContractualisee($debut, $fin): Mission
    {
        $declaration = Declaration::create([
            'etudiant_id' => $this->etudiant->id, 'type_mission' => 'stage', 'statut' => 'recevable',
            'date_debut_prevue' => $debut, 'date_fin_prevue' => $fin,
            'entreprise_saisie' => 'TestCorp', 'contact_nom' => 'J', 'contact_email' => 'ent@t.demo',
            'description' => 'Stage de développement web au sein d\'une petite équipe.',
        ]);
        $tuteurEntreprise = TuteurEntreprise::firstOrCreate(
            ['entreprise_id' => $this->entreprise->id, 'nom' => 'D', 'prenom' => 'E']);

        return Mission::create([
            'etudiant_id' => $this->etudiant->id, 'entreprise_id' => $this->entreprise->id,
            'declaration_id' => $declaration->id, 'tuteur_pedagogique_id' => $this->tuteur->id,
            'tuteur_entreprise_id' => $tuteurEntreprise->id, 'type' => 'stage',
            'date_debut' => $debut, 'date_fin' => $fin, 'statut' => 'contractualisee',
        ]);
    }

    private function jalon($echeance): Jalon
    {
        return Jalon::create(['mission_id' => $this->mission->id, 'type' => 'rapport_mensuel', 'date_echeance' => $echeance]);
    }

    public function test_les_etats_temporels_sont_calcules_depuis_les_dates(): void
    {
        $this->assertSame('active', $this->mission->statutCalcule());              // RG-29

        $future = $this->missionContractualiseeAutreEtudiant(now()->addMonth(), now()->addMonths(5));
        $this->assertSame('contractualisee', $future->statutCalcule());

        $this->mission->update(['date_debut' => now()->subMonths(6), 'date_fin' => now()->subMonth()]);
        $this->assertSame('en_evaluation', $this->mission->fresh()->statutCalcule());
        $this->assertSame('contractualisee', $this->mission->fresh()->statut);     // rien de stocké
    }

    private function missionContractualiseeAutreEtudiant($debut, $fin): Mission
    {
        $compte = Compte::create(['email' => uniqid().'@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant']);
        Etudiant::create(['compte_id' => $compte->id, 'promotion_id' => $this->mission->etudiant->promotion_id, 'nom' => 'X', 'prenom' => 'Y', 'statut_scolarite' => 'actif']);
        $declaration = Declaration::create([
            'etudiant_id' => $compte->id, 'type_mission' => 'stage', 'statut' => 'recevable',
            'date_debut_prevue' => $debut, 'date_fin_prevue' => $fin,
            'entreprise_saisie' => 'TestCorp', 'contact_nom' => 'J', 'contact_email' => 'ent@t.demo',
            'description' => 'Stage de développement web au sein d\'une petite équipe.',
        ]);

        return Mission::create([
            'etudiant_id' => $compte->id, 'entreprise_id' => $this->entreprise->id,
            'declaration_id' => $declaration->id, 'type' => 'stage',
            'date_debut' => $debut, 'date_fin' => $fin, 'statut' => 'contractualisee',
        ]);
    }

    public function test_depot_de_rapport_reserve_a_l_etudiant_de_la_mission(): void
    {
        $jalon = $this->jalon(now()->addDays(10));

        $this->actingAs($this->tuteur)->post('/jalons/'.$jalon->id.'/rapport',
            ['rapport' => UploadedFile::fake()->createWithContent('r.pdf', '%PDF-1.4 x')])->assertNotFound();

        $this->actingAs($this->etudiant)->post('/jalons/'.$jalon->id.'/rapport',
            ['rapport' => UploadedFile::fake()->createWithContent('r.pdf', '%PDF-1.4 x')])->assertRedirect();

        $jalon->refresh();
        Storage::assertExists($jalon->fichier_depose);
        $this->assertSame('rendu', $jalon->etat());                                // TV-23
    }

    public function test_jalon_echu_sans_depot_en_retard_puis_depot_tardif_historise(): void
    {
        $jalon = $this->jalon(now()->subDays(12));
        $this->assertSame('en_retard', $jalon->etat());                            // RG-39

        $this->actingAs($this->tuteur)->get('/tuteur/suivi')->assertOk()
            ->assertSee('en retard');                                              // visible du tuteur
        $this->actingAs($this->respA)->get('/tableau-de-bord')->assertOk()
            ->assertSee('jalon(s) en retard');                                     // et du responsable

        $this->actingAs($this->etudiant)->post('/jalons/'.$jalon->id.'/rapport',
            ['rapport' => UploadedFile::fake()->createWithContent('r.pdf', '%PDF-1.4 x')])->assertRedirect();
        $this->assertSame('rendu_tardif', $jalon->fresh()->etat());                // TV-23 A1 : historique lisible
    }

    public function test_rapport_telechargeable_par_les_bons_acteurs_seulement(): void
    {
        $jalon = $this->jalon(now()->subDay());
        $this->actingAs($this->etudiant)->post('/jalons/'.$jalon->id.'/rapport',
            ['rapport' => UploadedFile::fake()->createWithContent('r.pdf', '%PDF-1.4 x')]);

        foreach ([$this->etudiant, $this->tuteur, $this->respA] as $autorise) {
            $this->actingAs($autorise)->get('/jalons/'.$jalon->id.'/rapport')->assertOk();
        }
        $this->actingAs($this->entreprise)->get('/jalons/'.$jalon->id.'/rapport')->assertNotFound();
        $this->actingAs($this->respB)->get('/jalons/'.$jalon->id.'/rapport')->assertNotFound();
    }

    public function test_signalement_etudiant_pris_en_charge_puis_clos(): void
    {
        $this->actingAs($this->etudiant)->post('/suivi/missions/'.$this->mission->id.'/signaler',
            ['description' => 'Les tâches confiées ne correspondent plus à la mission prévue.'])->assertRedirect();
        $signalement = Signalement::sole();
        $this->assertSame('ouvert', $signalement->statut);                         // RG-40

        $this->actingAs($this->tuteur)->post('/signalements/'.$signalement->id.'/prendre')->assertRedirect();
        $signalement->refresh();
        $this->assertSame('en_cours', $signalement->statut);
        $this->assertSame($this->tuteur->id, $signalement->traitant_id);

        $this->actingAs($this->tuteur)->post('/signalements/'.$signalement->id.'/clore', [])
            ->assertSessionHasErrors('issue');                                     // issue obligatoire
        $this->actingAs($this->tuteur)->post('/signalements/'.$signalement->id.'/clore',
            ['issue' => 'Point tripartite organisé, missions recadrées.'])->assertRedirect();

        $signalement->refresh();
        $this->assertSame('clos', $signalement->statut);                           // TV-24
        $this->assertSame('Point tripartite organisé, missions recadrées.', $signalement->issue);
    }

    public function test_l_entreprise_peut_aussi_signaler(): void
    {
        $this->actingAs($this->entreprise)->post('/entreprise/missions/'.$this->mission->id.'/signaler',
            ['description' => 'L\'étudiant est absent sans justification depuis une semaine.'])->assertRedirect();

        $this->assertDatabaseHas('signalements', [
            'mission_id' => $this->mission->id, 'emetteur_id' => $this->entreprise->id, 'statut' => 'ouvert',
        ]);
    }

    public function test_cloisonnement_du_traitement_des_signalements(): void
    {
        $signalement = Signalement::create([
            'mission_id' => $this->mission->id, 'emetteur_id' => $this->etudiant->id,
            'description' => 'Une difficulté sérieuse à traiter rapidement.',
        ]);

        $autreTuteur = Compte::create(['email' => 'tut2@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'tuteur_pedagogique']);
        TuteurPedagogique::create(['compte_id' => $autreTuteur->id, 'etablissement_id' => $this->respB->responsable->etablissement_id, 'nom' => 'T', 'prenom' => '2', 'actif' => true]);

        $this->actingAs($autreTuteur)->post('/signalements/'.$signalement->id.'/prendre')->assertNotFound();
        $this->actingAs($this->respB)->post('/signalements/'.$signalement->id.'/prendre')->assertNotFound();
        $this->actingAs($this->etudiant)->post('/signalements/'.$signalement->id.'/prendre')->assertNotFound();

        $this->actingAs($this->respA)->post('/signalements/'.$signalement->id.'/prendre')->assertRedirect();
        $this->assertSame($this->respA->id, $signalement->fresh()->traitant_id);   // le responsable peut traiter
    }

    public function test_interruption_d_une_mission_active_conserve_les_rapports_rendus(): void
    {
        $rendu = $this->jalon(now()->subMonth());
        $this->actingAs($this->etudiant)->post('/jalons/'.$rendu->id.'/rapport',
            ['rapport' => UploadedFile::fake()->createWithContent('r.pdf', '%PDF-1.4 x')]);
        $nonRendu = $this->jalon(now()->addMonth());

        $this->actingAs($this->respA)->post('/ecole/missions/'.$this->mission->id.'/annuler', [
            'motif_arret' => 'Défaillance de l\'encadrement en entreprise.', 'date_effet_arret' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->mission->refresh();
        $this->assertSame('interrompue', $this->mission->statut);                  // RG-30 (mission active)
        $this->assertNotNull($rendu->fresh());                                     // dossier archivé en l'état
        $this->assertNull(Jalon::find($nonRendu->id));                             // échéancier arrêté

        // l'étudiant redevient libre de déclarer (RG-30)
        $this->actingAs($this->etudiant)->post('/declaration', [
            'type_mission' => 'stage', 'date_debut_prevue' => now()->addMonth()->format('Y-m-d'),
            'date_fin_prevue' => now()->addMonths(4)->format('Y-m-d'),
            'entreprise_saisie' => 'Autre SARL', 'contact_nom' => 'C', 'contact_email' => 'c@autre.demo',
            'description' => 'Nouvelle mission trouvée après interruption.',
        ])->assertSessionHasNoErrors();
    }

    public function test_annulation_avant_le_debut_reste_une_annulation(): void
    {
        $future = $this->missionContractualiseeAutreEtudiant(now()->addMonth(), now()->addMonths(5));

        $this->actingAs($this->respA)->post('/ecole/missions/'.$future->id.'/annuler', [
            'motif_arret' => 'Entreprise finalement défaillante.', 'date_effet_arret' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame('annulee', $future->fresh()->statut);                    // RG-30 (avant début)
    }
}
