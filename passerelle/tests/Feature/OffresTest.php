<?php

namespace Tests\Feature;

use App\Models\Compte;
use App\Models\Diffusion;
use App\Models\Domaine;
use App\Models\Entreprise;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Offre;
use App\Models\Partenariat;
use App\Models\Promotion;
use App\Models\Responsable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module partenariats & offres : RG-16 (partenariat requis), RG-17 (modération
 * indépendante), RG-18 (refus motivé), RG-19 (visibilité ciblée — TI-05/TV-10),
 * RG-20 (retrait avant validation seulement).
 */
class OffresTest extends TestCase
{
    use RefreshDatabase;

    private Compte $respA;
    private Compte $respB;
    private Compte $entreprise;
    private Etablissement $etabA;
    private Etablissement $etabB;
    private Promotion $promoA1;
    private Promotion $promoA2;
    private Domaine $domaine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domaine = Domaine::create(['code' => 'INFO', 'libelle' => 'Informatique']);

        [$this->etabA, $this->respA, $this->promoA1] = $this->ecole('École A', 'resp.a@t.demo', '00000000000001');
        [$this->etabB, $this->respB] = $this->ecole('École B', 'resp.b@t.demo', '00000000000002');
        $this->promoA2 = Promotion::create([
            'formation_id' => $this->promoA1->formation_id, 'libelle' => 'Promo A2', 'annee_universitaire' => '2026-2027',
        ]);

        $this->entreprise = Compte::create(['email' => 'ent@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $this->entreprise->id, 'raison_sociale' => 'TestCorp']);
        Partenariat::create(['etablissement_id' => $this->etabA->id, 'entreprise_id' => $this->entreprise->id, 'statut' => 'actif']);
    }

    private function ecole(string $nom, string $email, string $siret): array
    {
        $etab = Etablissement::create([
            'nom' => $nom, 'siret' => $siret, 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $compte = Compte::create(['email' => $email, 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $compte->id, 'etablissement_id' => $etab->id, 'nom' => 'R', 'prenom' => 'T']);
        $formation = Formation::create(['etablissement_id' => $etab->id, 'intitule' => 'M2', 'niveau' => 'Bac+5', 'type_mission' => 'alternance']);
        $promo = Promotion::create(['formation_id' => $formation->id, 'libelle' => 'Promo '.$nom, 'annee_universitaire' => '2026-2027']);

        return [$etab, $compte, $promo];
    }

    private function publier(array $ecoles): ?Offre
    {
        $this->actingAs($this->entreprise)->post('/entreprise/offres', [
            'intitule' => 'Alternant dev', 'description' => 'Mission de développement web.',
            'type' => 'alternance', 'niveau' => 'Bac+5', 'domaine_id' => $this->domaine->id,
            'lieu' => 'Lille', 'date_debut_prevue' => '2026-09-21', 'date_fin_prevue' => '2027-09-17',
            'nb_postes' => 1, 'etablissements' => $ecoles,
        ]);

        return Offre::latest('id')->first();
    }

    private function etudiantA(Promotion $promo, string $email): Compte
    {
        $compte = Compte::create(['email' => $email, 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant']);
        Etudiant::create(['compte_id' => $compte->id, 'promotion_id' => $promo->id, 'nom' => 'E', 'prenom' => 'T', 'statut_scolarite' => 'actif']);

        return $compte;
    }

    public function test_publication_reservee_aux_ecoles_partenaires_actives(): void
    {
        // l'école B n'est pas partenaire : la diffusion ne doit pas être créée
        $this->publier([$this->etabB->id]);

        $this->assertDatabaseCount('offres', 0);                                   // RG-16
        $this->assertDatabaseCount('diffusions', 0);
    }

    public function test_publication_cree_une_diffusion_soumise_par_ecole(): void
    {
        $offre = $this->publier([$this->etabA->id]);

        $this->assertDatabaseHas('diffusions', [
            'offre_id' => $offre->id, 'etablissement_id' => $this->etabA->id, 'statut' => 'soumise',
        ]);
    }

    public function test_moderation_validee_avec_promotions_ciblees(): void
    {
        $offre = $this->publier([$this->etabA->id]);
        $diffusion = $offre->diffusions()->first();

        $this->actingAs($this->respA)->post('/ecole/offres/'.$diffusion->id.'/valider', [
            'promotions' => [$this->promoA1->id],
        ])->assertRedirect();

        $this->assertSame('validee', $diffusion->fresh()->statut);
        $this->assertTrue($offre->promotions()->where('promotions.id', $this->promoA1->id)->exists());  // RG-19
    }

    public function test_le_refus_exige_un_motif_et_ne_touche_pas_les_autres_ecoles(): void
    {
        Partenariat::create(['etablissement_id' => $this->etabB->id, 'entreprise_id' => $this->entreprise->id, 'statut' => 'actif']);
        $offre = $this->publier([$this->etabA->id, $this->etabB->id]);
        [$dA, $dB] = [$offre->diffusions()->where('etablissement_id', $this->etabA->id)->first(),
                      $offre->diffusions()->where('etablissement_id', $this->etabB->id)->first()];

        $this->actingAs($this->respA)->post('/ecole/offres/'.$dA->id.'/refuser', [])->assertSessionHasErrors('motif_refus'); // RG-18
        $this->actingAs($this->respA)->post('/ecole/offres/'.$dA->id.'/refuser', ['motif_refus' => 'Hors référentiel.'])->assertRedirect();

        $this->assertSame('refusee', $dA->fresh()->statut);
        $this->assertSame('soumise', $dB->fresh()->statut);                        // RG-17 : indépendance
    }

    public function test_une_ecole_ne_modere_pas_les_diffusions_d_une_autre(): void
    {
        $offre = $this->publier([$this->etabA->id]);
        $diffusion = $offre->diffusions()->first();

        $this->actingAs($this->respB)->post('/ecole/offres/'.$diffusion->id.'/valider', [
            'promotions' => [$this->promoA1->id],
        ])->assertNotFound();                                                      // TI-02
    }

    public function test_l_affectation_est_limitee_aux_promotions_de_l_ecole(): void
    {
        Partenariat::create(['etablissement_id' => $this->etabB->id, 'entreprise_id' => $this->entreprise->id, 'statut' => 'actif']);
        $offre = $this->publier([$this->etabB->id]);
        $diffusion = $offre->diffusions()->first();

        // le responsable B tente de cibler une promotion de l'école A
        $this->actingAs($this->respB)->post('/ecole/offres/'.$diffusion->id.'/valider', [
            'promotions' => [$this->promoA1->id],
        ])->assertSessionHasErrors('promotions');                                  // TI-05

        $this->assertSame('soumise', $diffusion->fresh()->statut);
    }

    public function test_visibilite_etudiante_strictement_ciblee(): void
    {
        $offre = $this->publier([$this->etabA->id]);
        $diffusion = $offre->diffusions()->first();
        $this->actingAs($this->respA)->post('/ecole/offres/'.$diffusion->id.'/valider', ['promotions' => [$this->promoA1->id]]);

        $cible = $this->etudiantA($this->promoA1, 'cible@t.demo');
        $autrePromo = $this->etudiantA($this->promoA2, 'autre@t.demo');

        $this->actingAs($cible)->get('/offres')->assertOk()->assertSee('Alternant dev');           // TV-10
        $this->actingAs($cible)->get('/offres/'.$offre->id)->assertOk();
        $this->actingAs($autrePromo)->get('/offres')->assertOk()->assertDontSee('Alternant dev');  // promo non ciblée
        $this->actingAs($autrePromo)->get('/offres/'.$offre->id)->assertNotFound();
    }

    public function test_retrait_possible_avant_validation_bloque_apres(): void
    {
        $offre = $this->publier([$this->etabA->id]);
        $diffusion = $offre->diffusions()->first();

        // après validation par l'école A : retrait bloqué (RG-20)
        $this->actingAs($this->respA)->post('/ecole/offres/'.$diffusion->id.'/valider', ['promotions' => [$this->promoA1->id]]);
        $this->actingAs($this->entreprise)->post('/entreprise/offres/'.$offre->id.'/retirer')->assertSessionHasErrors('retrait');
        $this->assertSame('publiee', $offre->fresh()->statut);

        // offre jamais validée : retrait accepté, diffusions caduques
        $offre2 = $this->publier([$this->etabA->id]);
        $this->actingAs($this->entreprise)->post('/entreprise/offres/'.$offre2->id.'/retirer')->assertRedirect();
        $this->assertSame('retiree', $offre2->fresh()->statut);
        $this->assertSame('caduque', $offre2->diffusions()->first()->statut);
    }
}
