<?php

namespace Tests\Unit;

use App\Models\Compte;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TU-11 — invitations et jetons (RG-01, RG-02) :
 * expiration à 72 h, usage unique, renvoi invalidant l'ancien jeton.
 */
class InvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvitationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->service = app(InvitationService::class);
    }

    private function compte(string $email = 'invite@test.demo'): Compte
    {
        return Compte::create(['email' => $email, 'role' => 'entreprise']);
    }

    public function test_un_jeton_valide_permet_de_retrouver_l_invitation(): void
    {
        $compte = $this->compte();
        $jeton = $this->service->inviter($compte);

        $invitation = $this->service->valider($jeton);

        $this->assertNotNull($invitation);
        $this->assertSame($compte->id, $invitation->compte_id);
        // le jeton en clair n'est jamais stocké (RG-01)
        $this->assertDatabaseMissing('invitations', ['jeton_hash' => $jeton]);
    }

    public function test_un_jeton_expire_est_refuse(): void
    {
        $compte = $this->compte();
        $jeton = $this->service->inviter($compte);

        $this->travel(InvitationService::VALIDITE_HEURES + 1)->hours();

        $this->assertNull($this->service->valider($jeton));
    }

    public function test_un_jeton_est_a_usage_unique(): void
    {
        $compte = $this->compte();
        $jeton = $this->service->inviter($compte);

        $this->service->activer($this->service->valider($jeton), 'MotDePasseSur123');

        $this->assertNull($this->service->valider($jeton), 'Un jeton consommé ne doit plus être accepté (RG-02).');
    }

    public function test_le_renvoi_invalide_le_jeton_precedent(): void
    {
        $compte = $this->compte();
        $ancien = $this->service->inviter($compte);
        $nouveau = $this->service->inviter($compte);

        $this->assertNull($this->service->valider($ancien), "L'ancien jeton doit être invalidé au renvoi (RG-02).");
        $this->assertNotNull($this->service->valider($nouveau));
        $this->assertSame(1, Invitation::where('compte_id', $compte->id)->where('statut', 'active')->count());
    }

    public function test_l_activation_definit_le_mot_de_passe_et_le_statut_etudiant(): void
    {
        $etab = \App\Models\Etablissement::create([
            'nom' => 'École T', 'siret' => '00000000000000', 'ville' => 'Test',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31',
        ]);
        $formation = \App\Models\Formation::create([
            'etablissement_id' => $etab->id, 'intitule' => 'M2 Dev', 'niveau' => 'Bac+5', 'type_mission' => 'alternance',
        ]);
        $promotion = \App\Models\Promotion::create([
            'formation_id' => $formation->id, 'libelle' => 'M2 Dev', 'annee_universitaire' => '2026-2027',
        ]);
        $compte = Compte::create(['email' => 'etudiant@test.demo', 'role' => 'etudiant']);
        \App\Models\Etudiant::create([
            'compte_id' => $compte->id, 'promotion_id' => $promotion->id,
            'nom' => 'Test', 'prenom' => 'Étu', 'statut_scolarite' => 'invite',
        ]);

        $jeton = $this->service->inviter($compte);
        $this->service->activer($this->service->valider($jeton), 'MotDePasseSur123');

        $compte->refresh();
        $this->assertTrue(password_verify('MotDePasseSur123', $compte->mot_de_passe));
        $this->assertSame('actif', $compte->etudiant->statut_scolarite, "L'activation rend l'étudiant « actif » (RG-05).");
    }
}
