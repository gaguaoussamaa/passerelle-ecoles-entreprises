<?php

namespace Tests\Feature;

use App\Models\Compte;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Responsable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TU-12 (accès par statut, RG-05) et TU-13 partiel (rôles) au niveau des
 * routes réelles : connexion, refus, tableau de bord, révocation en session.
 */
class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    private function responsable(): Compte
    {
        $etab = Etablissement::create([
            'nom' => 'École T', 'siret' => '00000000000000', 'ville' => 'Test',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $compte = Compte::create([
            'email' => 'resp@test.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable',
        ]);
        Responsable::create([
            'compte_id' => $compte->id, 'etablissement_id' => $etab->id, 'nom' => 'Resp', 'prenom' => 'Test',
        ]);

        return $compte;
    }

    private function etudiant(string $statut): Compte
    {
        $etab = Etablissement::create([
            'nom' => 'École E', 'siret' => '00000000000001', 'ville' => 'Test',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $formation = Formation::create([
            'etablissement_id' => $etab->id, 'intitule' => 'M2', 'niveau' => 'Bac+5', 'type_mission' => 'stage',
        ]);
        $promotion = Promotion::create([
            'formation_id' => $formation->id, 'libelle' => 'M2', 'annee_universitaire' => '2026-2027',
        ]);
        $compte = Compte::create([
            'email' => 'etu@test.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant',
        ]);
        Etudiant::create([
            'compte_id' => $compte->id, 'promotion_id' => $promotion->id,
            'nom' => 'Étu', 'prenom' => 'Test', 'statut_scolarite' => $statut,
        ]);

        return $compte;
    }

    public function test_la_page_de_connexion_est_servie(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Connexion');
    }

    public function test_un_responsable_actif_se_connecte_et_voit_son_tableau_de_bord(): void
    {
        $this->responsable();

        $this->post('/connexion', ['email' => 'resp@test.demo', 'mot_de_passe' => 'MotDePasseSur123'])
            ->assertRedirect('/tableau-de-bord');

        $this->assertAuthenticated();
        $this->get('/tableau-de-bord')->assertOk()->assertSee('École T');
    }

    public function test_un_mauvais_mot_de_passe_est_refuse(): void
    {
        $this->responsable();

        $this->from('/connexion')
            ->post('/connexion', ['email' => 'resp@test.demo', 'mot_de_passe' => 'mauvais-mdp-000'])
            ->assertRedirect('/connexion');

        $this->assertGuest();
    }

    public function test_un_etudiant_sorti_ne_peut_pas_se_connecter(): void
    {
        $this->etudiant('sorti');

        $this->from('/connexion')
            ->post('/connexion', ['email' => 'etu@test.demo', 'mot_de_passe' => 'MotDePasseSur123'])
            ->assertRedirect('/connexion');

        $this->assertGuest(); // RG-05
    }

    public function test_un_etudiant_actif_se_connecte(): void
    {
        $this->etudiant('actif');

        $this->post('/connexion', ['email' => 'etu@test.demo', 'mot_de_passe' => 'MotDePasseSur123'])
            ->assertRedirect('/tableau-de-bord');

        $this->assertAuthenticated();
    }

    public function test_la_revocation_du_statut_coupe_une_session_ouverte(): void
    {
        $compte = $this->etudiant('actif');
        $this->actingAs($compte);
        $this->get('/tableau-de-bord')->assertOk();

        $compte->etudiant->update(['statut_scolarite' => 'sorti']);

        $this->get('/tableau-de-bord')->assertRedirect('/connexion'); // middleware « acces »
        $this->assertGuest();
    }

    public function test_le_tableau_de_bord_exige_l_authentification(): void
    {
        $this->get('/tableau-de-bord')->assertRedirect('/connexion');
    }
}
