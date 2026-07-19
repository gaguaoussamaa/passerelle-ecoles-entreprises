<?php

namespace App\Http\Controllers\Ecole;

use App\Models\Compte;
use App\Models\Entreprise;
use App\Models\Partenariat;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PartenaireController extends ControleurEcole
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function index(): View
    {
        return view('ecole.partenaires.index', [
            'partenariats' => Partenariat::where('etablissement_id', $this->etablissement()->id)
                ->with('entreprise.compte')->orderBy('statut')->get(),
        ]);
    }

    /** EF-10 : invitation d'une entreprise ; partenariat actif à l'activation (RG-47). */
    public function inviter(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        $compteExistant = Compte::where('email', $donnees['email'])->first();

        if ($compteExistant && $compteExistant->role !== 'entreprise') {
            return back()->withErrors(['email' => 'Cette adresse est déjà utilisée par un autre type de compte.']);
        }

        if ($compteExistant) {          // entreprise déjà sur la plateforme : partenariat direct
            Partenariat::firstOrCreate(
                ['etablissement_id' => $this->etablissement()->id, 'entreprise_id' => $compteExistant->id],
                ['statut' => 'actif'],
            );

            return back()->with('statut', "Entreprise déjà présente : partenariat activé avec {$donnees['raison_sociale']}.");
        }

        $compte = DB::transaction(function () use ($donnees) {
            $compte = Compte::create(['email' => $donnees['email'], 'role' => 'entreprise']);
            Entreprise::create(['compte_id' => $compte->id, 'raison_sociale' => $donnees['raison_sociale']]);
            Partenariat::create([
                'etablissement_id' => $this->etablissement()->id,
                'entreprise_id' => $compte->id,
                'statut' => 'en_attente',
            ]);

            return $compte;
        });

        $this->invitations->inviter($compte);

        return back()->with('statut', "Invitation envoyée à {$donnees['email']} — le partenariat s'activera avec le compte.");
    }
}
