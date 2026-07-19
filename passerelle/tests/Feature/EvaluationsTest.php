<?php

namespace Tests\Feature;

use App\Models\Compte;
use App\Models\Critere;
use App\Models\Declaration;
use App\Models\Entreprise;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evaluation;
use App\Models\Formation;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Promotion;
use App\Models\Responsable;
use App\Models\TuteurEntreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Évaluation et clôture (UC-17 — TV-26) : grille standard par le tuteur en
 * entreprise (RG-41), clôture par le responsable exigeant l'évaluation, jalons
 * non rendus consignés sans bloquer, dossier archivé consultable (RG-42).
 */
class EvaluationsTest extends TestCase
{
    use RefreshDatabase;

    private Compte $respA;
    private Compte $respB;
    private Compte $entreprise;
    private Compte $etudiant;
    private Mission $mission;
    private Jalon $jalonNonRendu;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['c1', 'Intégration'], ['c2', 'Compétences'], ['c3', 'Autonomie']] as [$code, $libelle]) {
            Critere::create(['code' => $code, 'libelle' => $libelle]);
        }

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

        $this->mission = $this->mission(now()->subMonths(5), now()->subWeeks(2));   // terminée → en évaluation
        $this->jalonNonRendu = Jalon::create([
            'mission_id' => $this->mission->id, 'type' => 'rapport_mensuel', 'date_echeance' => now()->subMonth(),
        ]);
    }

    private function mission($debut, $fin): Mission
    {
        $declaration = Declaration::create([
            'etudiant_id' => $this->etudiant->id, 'type_mission' => 'stage', 'statut' => 'recevable',
            'date_debut_prevue' => $debut, 'date_fin_prevue' => $fin,
            'entreprise_saisie' => 'TestCorp', 'contact_nom' => 'J', 'contact_email' => 'ent@t.demo',
            'description' => 'Stage de développement web au sein d\'une petite équipe.',
        ]);
        $tuteur = TuteurEntreprise::firstOrCreate(['entreprise_id' => $this->entreprise->id, 'nom' => 'D', 'prenom' => 'E']);

        return Mission::create([
            'etudiant_id' => $this->etudiant->id, 'entreprise_id' => $this->entreprise->id,
            'declaration_id' => $declaration->id, 'tuteur_entreprise_id' => $tuteur->id,
            'type' => 'stage', 'date_debut' => $debut, 'date_fin' => $fin, 'statut' => 'contractualisee',
        ]);
    }

    private function evaluer(Mission $mission, array $notes = ['c1' => 4, 'c2' => 5, 'c3' => 3])
    {
        return $this->actingAs($this->entreprise)->post('/entreprise/missions/'.$mission->id.'/evaluation', [
            'notes' => Critere::all()->mapWithKeys(fn ($c) => [$c->id => $notes[$c->code] ?? 3])->all(),
            'commentaire' => 'Très bon stage, progression continue.',
        ]);
    }

    public function test_la_grille_est_remplie_par_le_tuteur_en_entreprise(): void
    {
        $this->evaluer($this->mission)->assertRedirect();                          // RG-41

        $evaluation = Evaluation::sole();
        $this->assertSame($this->mission->tuteur_entreprise_id, $evaluation->tuteur_entreprise_id);
        $this->assertSame(3, $evaluation->criteres()->count());
        $this->assertSame(5, $evaluation->criteres()->where('code', 'c2')->first()->pivot->note);
        $this->assertSame('Très bon stage, progression continue.', $evaluation->commentaire);

        $this->evaluer($this->mission)->assertNotFound();                          // une seule évaluation
        $this->assertDatabaseCount('evaluations', 1);
    }

    public function test_note_hors_bareme_refusee(): void
    {
        $this->evaluer($this->mission, ['c1' => 6])->assertSessionHasErrors();
        $this->assertDatabaseCount('evaluations', 0);
    }

    public function test_evaluation_impossible_avant_la_fin_de_mission(): void
    {
        $active = $this->mission(now()->subMonth(), now()->addMonths(2));

        $this->evaluer($active)->assertNotFound();
    }

    public function test_evaluation_cloisonnee_a_l_entreprise_de_la_mission(): void
    {
        $autre = Compte::create(['email' => 'ent2@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $autre->id, 'raison_sociale' => 'AutreCorp']);

        $this->actingAs($autre)->post('/entreprise/missions/'.$this->mission->id.'/evaluation', [
            'notes' => Critere::all()->mapWithKeys(fn ($c) => [$c->id => 3])->all(),
        ])->assertNotFound();
    }

    public function test_cloture_bloquee_sans_evaluation_puis_acceptee_avec_jalon_consigne(): void
    {
        $this->actingAs($this->respA)->post('/ecole/missions/'.$this->mission->id.'/cloturer')
            ->assertSessionHasErrors('cloture');                                   // TV-26 : blocage explicite
        $this->assertSame('contractualisee', $this->mission->fresh()->statut);

        $this->evaluer($this->mission);
        $this->actingAs($this->respA)->post('/ecole/missions/'.$this->mission->id.'/cloturer')->assertRedirect();

        $this->assertSame('cloturee', $this->mission->fresh()->statut);            // RG-42
        $this->assertNotNull($this->jalonNonRendu->fresh());                       // conservé au dossier
        $this->assertDatabaseHas('journal_audit', [
            'action' => 'mission_cloturee', 'objet_id' => $this->mission->id,
            'details' => '1 jalon(s) non rendu(s) consigné(s) au dossier',
        ]);

        // dossier archivé consultable : grille et statut visibles du responsable
        $this->actingAs($this->respA)->get('/ecole/missions')->assertOk()
            ->assertSee('clôturée')->assertSee('Compétences');
    }

    public function test_cloture_impossible_avant_le_terme(): void
    {
        $active = $this->mission(now()->subMonth(), now()->addMonths(2));

        $this->actingAs($this->respA)->post('/ecole/missions/'.$active->id.'/cloturer')
            ->assertSessionHasErrors('cloture');
    }

    public function test_cloture_reservee_au_responsable_de_l_ecole(): void
    {
        $this->evaluer($this->mission);

        $this->actingAs($this->respB)->post('/ecole/missions/'.$this->mission->id.'/cloturer')->assertNotFound();
        $this->actingAs($this->entreprise)->post('/ecole/missions/'.$this->mission->id.'/cloturer')->assertForbidden();  // middleware de rôle
    }

    public function test_verrous_supplementaires(): void
    {
        // double clôture impossible : le dossier clôturé n'est plus dans le périmètre d'action
        $this->evaluer($this->mission);
        $this->actingAs($this->respA)->post('/ecole/missions/'.$this->mission->id.'/cloturer');
        $this->actingAs($this->respA)->post('/ecole/missions/'.$this->mission->id.'/cloturer')->assertNotFound();
    }

    public function test_l_etudiant_est_libre_apres_cloture(): void
    {
        $this->evaluer($this->mission);
        $this->actingAs($this->respA)->post('/ecole/missions/'.$this->mission->id.'/cloturer');

        $this->actingAs($this->etudiant)->post('/declaration', [
            'type_mission' => 'stage', 'date_debut_prevue' => now()->addMonth()->format('Y-m-d'),
            'date_fin_prevue' => now()->addMonths(4)->format('Y-m-d'),
            'entreprise_saisie' => 'Autre SARL', 'contact_nom' => 'C', 'contact_email' => 'c@autre.demo',
            'description' => 'Nouvelle mission après la clôture de la précédente.',
        ])->assertSessionHasNoErrors();
    }
}
