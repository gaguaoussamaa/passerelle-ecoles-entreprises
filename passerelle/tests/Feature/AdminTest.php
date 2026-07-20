<?php

namespace Tests\Feature;

use App\Mail\InvitationMail;
use App\Models\Compte;
use App\Models\Etablissement;
use App\Models\Responsable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UC-19 (EF-01/EF-03 — TV-01) : création d'un espace établissement par le
 * super-administrateur, plan d'abonnement (RG-44), premier responsable invité.
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    private Compte $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = Compte::create(['email' => 'admin@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'super_admin']);
    }

    private function creer(array $surcharges = [])
    {
        return $this->actingAs($this->admin)->post('/admin/etablissements', $surcharges + [
            'nom' => 'École Nouvelle', 'siret' => '12345678900011', 'ville' => 'Nantes',
            'plan_abonnement' => 'standard', 'debut_abonnement' => '2026-09-01', 'fin_abonnement' => '2027-08-31',
            'responsable_prenom' => 'Nadia', 'responsable_nom' => 'Robert', 'responsable_email' => 'n.robert@nouvelle.demo',
        ]);
    }

    public function test_creation_de_l_espace_et_invitation_du_premier_responsable(): void
    {
        $this->creer()->assertRedirect();                                          // TV-01

        $etablissement = Etablissement::sole();
        $this->assertSame('standard', $etablissement->plan_abonnement);            // RG-44
        $compte = Compte::where('email', 'n.robert@nouvelle.demo')->sole();
        $this->assertSame('responsable', $compte->role);
        $this->assertNull($compte->mot_de_passe);                                  // invité
        $this->assertDatabaseHas('responsables', ['compte_id' => $compte->id, 'etablissement_id' => $etablissement->id]);
        Mail::assertSent(InvitationMail::class, fn ($m) => $m->hasTo('n.robert@nouvelle.demo'));
    }

    public function test_reserve_au_super_administrateur(): void
    {
        $etab = Etablissement::create(['nom' => 'E', 'siret' => '00000000000001', 'ville' => 'T',
            'plan_abonnement' => 'essentiel', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31']);
        $responsable = Compte::create(['email' => 'resp@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $responsable->id, 'etablissement_id' => $etab->id, 'nom' => 'R', 'prenom' => 'T']);

        $this->actingAs($responsable)->get('/admin/etablissements')->assertForbidden();
        $this->actingAs($responsable)->post('/admin/etablissements', [])->assertForbidden();
    }

    public function test_coherence_des_donnees_exigee(): void
    {
        $this->creer(['fin_abonnement' => '2026-08-31'])->assertSessionHasErrors('fin_abonnement');
        $this->creer(['plan_abonnement' => 'illimite'])->assertSessionHasErrors('plan_abonnement');
        $this->creer(['siret' => '123'])->assertSessionHasErrors('siret');
        $this->assertDatabaseCount('etablissements', 0);

        Compte::create(['email' => 'pris@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'etudiant']);
        $this->creer(['responsable_email' => 'pris@t.demo'])->assertSessionHasErrors('responsable_email');
    }

    public function test_abonnement_visible_en_lecture_seule_par_le_responsable(): void
    {
        $etab = Etablissement::create(['nom' => 'E', 'siret' => '00000000000001', 'ville' => 'T',
            'plan_abonnement' => 'premium', 'debut_abonnement' => '2026-01-01', 'fin_abonnement' => '2026-12-31']);
        $responsable = Compte::create(['email' => 'resp@t.demo', 'mot_de_passe' => 'MotDePasseSur123', 'role' => 'responsable']);
        Responsable::create(['compte_id' => $responsable->id, 'etablissement_id' => $etab->id, 'nom' => 'R', 'prenom' => 'T']);

        $this->actingAs($responsable)->get('/tableau-de-bord')->assertOk()
            ->assertSee('Mon abonnement')->assertSee('Premium')                    // RG-44
            ->assertSee('01/01/2026');
    }
}
