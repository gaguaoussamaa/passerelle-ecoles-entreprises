<?php

namespace Tests\Feature;

use App\Mail\CandidatureEntrepriseMail;
use App\Mail\CandidatureStatutMail;
use App\Models\Candidature;
use App\Models\Compte;
use App\Models\Diffusion;
use App\Models\Domaine;
use App\Models\Entreprise;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Mission;
use App\Models\Offre;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Module candidatures : RG-22 (unicité), RG-23 (états + notification), RG-24
 * (retrait avant « retenue »), RG-25/26 (confirmation → mission chemin A,
 * autres candidatures retirées), RG-48 (CV figé au dépôt) — TV-12/13/14.
 */
class CandidaturesTest extends TestCase
{
    use RefreshDatabase;

    private Compte $entreprise;
    private Compte $cible;
    private Etablissement $etab;
    private Promotion $promoA1;
    private Promotion $promoA2;
    private Domaine $domaine;
    private Offre $offre;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->domaine = Domaine::create(['code' => 'INFO', 'libelle' => 'Informatique']);
        $this->etab = Etablissement::create([
            'nom' => 'École A', 'siret' => '00000000000001', 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $formation = Formation::create(['etablissement_id' => $this->etab->id, 'intitule' => 'M2', 'niveau' => 'Bac+5', 'type_mission' => 'alternance']);
        $this->promoA1 = Promotion::create(['formation_id' => $formation->id, 'libelle' => 'Promo A1', 'annee_universitaire' => '2026-2027']);
        $this->promoA2 = Promotion::create(['formation_id' => $formation->id, 'libelle' => 'Promo A2', 'annee_universitaire' => '2026-2027']);

        $this->entreprise = Compte::create(['email' => 'ent@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $this->entreprise->id, 'raison_sociale' => 'TestCorp']);

        $this->cible = $this->etudiant($this->promoA1, 'cible@t.demo');
        $this->offre = $this->offrePubliee();
    }

    private function etudiant(Promotion $promo, string $email): Compte
    {
        $compte = Compte::create(['email' => $email, 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant']);
        Etudiant::create(['compte_id' => $compte->id, 'promotion_id' => $promo->id, 'nom' => 'E', 'prenom' => 'T', 'statut_scolarite' => 'actif']);

        return $compte;
    }

    /** Offre publiée, validée par l'école A et affectée à la promotion A1. */
    private function offrePubliee(string $intitule = 'Alternant dev'): Offre
    {
        $offre = Offre::create([
            'entreprise_id' => $this->entreprise->id, 'domaine_id' => $this->domaine->id,
            'intitule' => $intitule, 'description' => 'Mission de développement web.',
            'type' => 'alternance', 'niveau' => 'Bac+5', 'lieu' => 'Lille', 'statut' => 'publiee',
            'date_debut_prevue' => '2026-09-21', 'date_fin_prevue' => '2027-09-17', 'nb_postes' => 1,
        ]);
        Diffusion::create(['offre_id' => $offre->id, 'etablissement_id' => $this->etab->id, 'statut' => 'validee']);
        $offre->promotions()->sync([$this->promoA1->id]);

        return $offre;
    }

    private function deposerCv(Compte $etudiant, string $contenu = "%PDF-1.4 version-1"): void
    {
        $this->actingAs($etudiant)->post('/candidatures/cv', [
            'cv' => UploadedFile::fake()->createWithContent('cv.pdf', $contenu),
        ])->assertRedirect();
    }

    private function candidater(Compte $etudiant, Offre $offre)
    {
        return $this->actingAs($etudiant)->post('/offres/'.$offre->id.'/candidater', [
            'message' => 'Ma motivation pour cette mission de développement.',
        ]);
    }

    public function test_depot_avec_copie_du_cv_et_notification_entreprise(): void
    {
        $this->deposerCv($this->cible);
        $this->candidater($this->cible, $this->offre)->assertRedirect('/candidatures');

        $candidature = Candidature::sole();
        $this->assertSame('recue', $candidature->statut);                          // défaut RG-23
        $this->assertNotSame($this->cible->etudiant->fresh()->cv_profil, $candidature->cv_depose);
        Storage::assertExists($candidature->cv_depose);                            // copie physique (RG-48, TI-06)
        Mail::assertSent(CandidatureEntrepriseMail::class, fn ($m) => $m->hasTo('ent@t.demo') && $m->evenement === 'deposee');
    }

    public function test_seconde_candidature_refusee_proprement(): void
    {
        $this->deposerCv($this->cible);
        $this->candidater($this->cible, $this->offre);

        $this->candidater($this->cible, $this->offre)->assertSessionHasErrors('candidature');  // RG-22 / TU-17
        $this->assertDatabaseCount('candidatures', 1);
    }

    public function test_candidature_impossible_sans_cv(): void
    {
        $this->candidater($this->cible, $this->offre)->assertSessionHasErrors('cv');
        $this->assertDatabaseCount('candidatures', 0);
    }

    public function test_candidature_impossible_hors_visibilite(): void
    {
        $autrePromo = $this->etudiant($this->promoA2, 'autre@t.demo');
        $this->deposerCv($autrePromo);

        $this->candidater($autrePromo, $this->offre)->assertNotFound();            // cloisonnement RG-19
        $this->assertDatabaseCount('candidatures', 0);
    }

    public function test_remplacer_le_cv_du_profil_ne_change_pas_la_candidature(): void
    {
        $this->deposerCv($this->cible, '%PDF-1.4 version-1');
        $this->candidater($this->cible, $this->offre);
        $candidature = Candidature::sole();

        $this->deposerCv($this->cible, '%PDF-1.4 version-2');                      // TV-12

        $this->assertSame('%PDF-1.4 version-1', Storage::get($candidature->cv_depose));   // RG-48 : figé
        $this->assertSame('%PDF-1.4 version-2', Storage::get($this->cible->etudiant->fresh()->cv_profil));
    }

    public function test_retrait_possible_avant_retenue_seulement(): void
    {
        $this->deposerCv($this->cible);
        $this->candidater($this->cible, $this->offre);
        $candidature = Candidature::sole();

        $this->actingAs($this->cible)->post('/candidatures/'.$candidature->id.'/retirer')->assertRedirect();
        $this->assertSame('retiree', $candidature->fresh()->statut);               // RG-24

        $retenue = Candidature::create([
            'etudiant_id' => $this->cible->id, 'offre_id' => $this->offrePubliee('Autre offre')->id,
            'statut' => 'retenue', 'message' => 'x', 'cv_depose' => 'cv/candidatures/x.pdf',
        ]);
        $this->actingAs($this->cible)->post('/candidatures/'.$retenue->id.'/retirer')->assertSessionHasErrors('retrait');
        $this->assertSame('retenue', $retenue->fresh()->statut);
    }

    public function test_l_entreprise_instruit_le_dossier_et_l_etudiant_est_notifie(): void
    {
        $this->deposerCv($this->cible);
        $this->candidater($this->cible, $this->offre);
        $candidature = Candidature::sole();

        $autreEntreprise = Compte::create(['email' => 'ent2@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $autreEntreprise->id, 'raison_sociale' => 'AutreCorp']);
        $this->actingAs($autreEntreprise)->post('/entreprise/candidatures/'.$candidature->id.'/statut', ['statut' => 'retenue'])
            ->assertNotFound();                                                    // cloisonnement

        $this->actingAs($this->entreprise)->post('/entreprise/candidatures/'.$candidature->id.'/statut', ['statut' => 'confirmee'])
            ->assertSessionHasErrors('statut');                                    // transition hors périmètre entreprise

        foreach (['preselectionnee', 'entretien', 'retenue'] as $statut) {         // TU-04
            $this->actingAs($this->entreprise)->post('/entreprise/candidatures/'.$candidature->id.'/statut', ['statut' => $statut])
                ->assertRedirect();
            $this->assertSame($statut, $candidature->fresh()->statut);
        }
        Mail::assertSent(CandidatureStatutMail::class, 3);                         // RG-23 : notifié à chaque changement
        Mail::assertSent(CandidatureStatutMail::class, fn ($m) => $m->hasTo('cible@t.demo'));

        $this->actingAs($this->entreprise)->post('/entreprise/candidatures/'.$candidature->id.'/statut', ['statut' => 'refusee'])
            ->assertNotFound();                                                    // « retenue » : la main passe à l'étudiant
    }

    public function test_la_confirmation_cree_la_mission_et_retire_les_autres_candidatures(): void
    {
        $offre2 = $this->offrePubliee('Deuxième offre');
        $this->deposerCv($this->cible);
        $this->candidater($this->cible, $this->offre);
        $this->candidater($this->cible, $offre2);
        [$candidature, $autre] = [
            Candidature::where('offre_id', $this->offre->id)->sole(),
            Candidature::where('offre_id', $offre2->id)->sole(),
        ];
        $candidature->update(['statut' => 'retenue']);

        $this->actingAs($this->cible)->post('/candidatures/'.$candidature->id.'/confirmer')->assertRedirect();

        $this->assertSame('confirmee', $candidature->fresh()->statut);             // TV-13
        $mission = Mission::sole();
        $this->assertSame($candidature->id, $mission->candidature_id);             // origine chemin A (RG-26)
        $this->assertNull($mission->declaration_id);
        $this->assertSame('en_montage', $mission->statut);
        $this->assertSame('alternance', $mission->type);
        $this->assertSame('2026-09-21', $mission->date_debut->format('Y-m-d'));
        $this->assertSame('retiree', $autre->fresh()->statut);                     // RG-25
        $this->assertDatabaseHas('journal_audit', [
            'action' => 'candidature_retiree_auto', 'objet_type' => 'candidature', 'objet_id' => $autre->id,
        ]);
        Mail::assertSent(CandidatureEntrepriseMail::class, fn ($m) => $m->evenement === 'confirmee');
    }

    public function test_la_declinaison_ne_cree_pas_de_mission(): void
    {
        $this->deposerCv($this->cible);
        $this->candidater($this->cible, $this->offre);
        $candidature = Candidature::sole();
        $candidature->update(['statut' => 'retenue']);

        $this->actingAs($this->cible)->post('/candidatures/'.$candidature->id.'/decliner')->assertRedirect();

        $this->assertSame('declinee', $candidature->fresh()->statut);              // TV-14
        $this->assertDatabaseCount('missions', 0);
        $this->assertSame('publiee', $this->offre->fresh()->statut);               // l'offre reste ouverte
    }

    public function test_le_cv_depose_est_reserve_aux_bons_acteurs(): void
    {
        $this->deposerCv($this->cible);
        $this->candidater($this->cible, $this->offre);
        $candidature = Candidature::sole();

        $this->actingAs($this->entreprise)->get('/entreprise/candidatures/'.$candidature->id.'/cv')->assertOk();
        $this->actingAs($this->cible)->get('/candidatures/'.$candidature->id.'/cv')->assertOk();

        $autreEntreprise = Compte::create(['email' => 'ent2@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'entreprise']);
        Entreprise::create(['compte_id' => $autreEntreprise->id, 'raison_sociale' => 'AutreCorp']);
        $this->actingAs($autreEntreprise)->get('/entreprise/candidatures/'.$candidature->id.'/cv')->assertNotFound();

        $autreEtudiant = $this->etudiant($this->promoA1, 'autre-etu@t.demo');
        $this->actingAs($autreEtudiant)->get('/candidatures/'.$candidature->id.'/cv')->assertNotFound();
    }
}
