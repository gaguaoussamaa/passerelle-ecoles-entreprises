<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compte;
use App\Models\Etablissement;
use App\Models\JournalAudit;
use App\Models\Responsable;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC-19 (EF-01/EF-03) : le super-administrateur crée l'espace établissement,
 * attribue le plan d'abonnement et ses dates (RG-44) et invite le premier
 * responsable, qui paramètre ensuite son espace en autonomie.
 */
class EtablissementController extends Controller
{
    public const PLANS = ['essentiel', 'standard', 'premium'];

    public function __construct(private readonly InvitationService $invitations) {}

    public function index(): View
    {
        return view('admin.etablissements.index', [
            'etablissements' => Etablissement::withCount('responsables')->orderBy('nom')->get(),
            'plans' => self::PLANS,
        ]);
    }

    public function creer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'siret' => ['required', 'digits:14'],
            'ville' => ['required', 'string', 'max:100'],
            'plan_abonnement' => ['required', Rule::in(self::PLANS)],              // RG-44
            'debut_abonnement' => ['required', 'date'],
            'fin_abonnement' => ['required', 'date', 'after_or_equal:debut_abonnement'],
            'responsable_prenom' => ['required', 'string', 'max:80'],
            'responsable_nom' => ['required', 'string', 'max:80'],
            'responsable_email' => ['required', 'email', 'max:190', 'unique:comptes,email'],
        ]);

        DB::transaction(function () use ($donnees) {
            $etablissement = Etablissement::create($donnees);
            $compte = Compte::create(['email' => $donnees['responsable_email'], 'role' => 'responsable']);
            Responsable::create([
                'compte_id' => $compte->id, 'etablissement_id' => $etablissement->id,
                'prenom' => $donnees['responsable_prenom'], 'nom' => $donnees['responsable_nom'],
            ]);
            JournalAudit::tracer('etablissement_cree', 'etablissement', $etablissement->id, auth()->id(),
                'plan '.$donnees['plan_abonnement']);
            $this->invitations->inviter($compte);                                  // invitation automatique
        });

        return back()->with('succes', 'Espace créé : le responsable a reçu son invitation d\'activation.');
    }
}
