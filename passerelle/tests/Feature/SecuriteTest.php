<?php

namespace Tests\Feature;

use App\Models\Compte;
use App\Models\Etablissement;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Responsable;
use App\Models\Etudiant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Durcissements de sécurité (demande tuteur, référentiels OWASP Top 10:2025 A07,
 * CNIL 2022) : message de connexion générique (anti-énumération), limitation des
 * tentatives (anti brute force), en-têtes de sécurité HTTP, politique de mot de passe.
 */
class SecuriteTest extends TestCase
{
    use RefreshDatabase;

    private function responsableActif(): Compte
    {
        $etab = Etablissement::create([
            'nom' => 'École S', 'siret' => '00000000000009', 'ville' => 'Test',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $compte = Compte::create([
            'email' => 'resp@secu.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable',
        ]);
        Responsable::create(['compte_id' => $compte->id, 'etablissement_id' => $etab->id, 'nom' => 'R', 'prenom' => 'S']);

        return $compte;
    }

    private function etudiantInvite(): Compte
    {
        $etab = Etablissement::create([
            'nom' => 'École I', 'siret' => '00000000000010', 'ville' => 'Test',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $formation = Formation::create([
            'etablissement_id' => $etab->id, 'intitule' => 'M2', 'niveau' => 'Bac+5', 'type_mission' => 'stage',
        ]);
        $promotion = Promotion::create([
            'formation_id' => $formation->id, 'libelle' => 'M2', 'annee_universitaire' => '2026-2027',
        ]);
        $compte = Compte::create([
            'email' => 'invite@secu.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant',
        ]);
        Etudiant::create([
            'compte_id' => $compte->id, 'promotion_id' => $promotion->id,
            'nom' => 'I', 'prenom' => 'S', 'statut_scolarite' => 'invite', // sans accès
        ]);

        return $compte;
    }

    /**
     * Anti-énumération : e-mail inconnu, mot de passe faux sur compte actif, et
     * mot de passe faux sur compte inactif renvoient TOUS le même message.
     */
    public function test_le_message_de_connexion_est_generique(): void
    {
        $this->responsableActif();
        $this->etudiantInvite();

        $this->from('/connexion')->post('/connexion', ['email' => 'inconnu@secu.demo', 'mot_de_passe' => 'FauxFaux12345'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        $this->from('/connexion')->post('/connexion', ['email' => 'resp@secu.demo', 'mot_de_passe' => 'FauxFaux12345'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        // compte existant mais inactif + mot de passe faux : toujours le message générique
        // (aucune fuite d'existence tant que le mot de passe n'est pas prouvé).
        $this->from('/connexion')->post('/connexion', ['email' => 'invite@secu.demo', 'mot_de_passe' => 'FauxFaux12345'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);
    }

    public function test_les_tentatives_de_connexion_sont_limitees(): void
    {
        $this->responsableActif();

        // 5 échecs autorisés
        for ($i = 0; $i < 5; $i++) {
            $this->from('/connexion')->post('/connexion', ['email' => 'resp@secu.demo', 'mot_de_passe' => 'FauxFaux12345'])
                ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);
        }

        // 6e tentative bloquée, même avec le BON mot de passe : le verrouillage prime
        // (le bon mot de passe n'ouvre pas de session) et le message l'annonce.
        $this->followingRedirects()->from('/connexion')
            ->post('/connexion', ['email' => 'resp@secu.demo', 'mot_de_passe' => 'MotDePasseSur123'])
            ->assertSee('Trop de tentatives');
        $this->assertGuest();
    }

    public function test_les_entetes_de_securite_sont_presents(): void
    {
        $this->get('/connexion')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_l_activation_exige_un_mot_de_passe_robuste(): void
    {
        // 11 caractères : rejeté par la règle de longueur (≥ 12).
        $this->post('/activation', [
            'jeton' => 'jeton-inexistant',
            'mot_de_passe' => 'onze1234567', 'mot_de_passe_confirmation' => 'onze1234567',
        ])->assertSessionHasErrors('mot_de_passe');

        // 12 caractères mais SANS chiffre : rejeté par la règle de composition (lettres + chiffres).
        $this->post('/activation', [
            'jeton' => 'jeton-inexistant',
            'mot_de_passe' => 'abcdefghijkl', 'mot_de_passe_confirmation' => 'abcdefghijkl',
        ])->assertSessionHasErrors('mot_de_passe');

        // 12 caractères, lettres ET chiffres : la validation du mot de passe passe ; l'échec
        // porte alors sur le jeton (erreur « email »), ce qui prouve la règle franchie.
        $reponse = $this->post('/activation', [
            'jeton' => 'jeton-inexistant',
            'mot_de_passe' => 'douze1234567', 'mot_de_passe_confirmation' => 'douze1234567',
        ]);
        $reponse->assertSessionHasErrors('email');
        $reponse->assertSessionDoesntHaveErrors('mot_de_passe');
    }
}
