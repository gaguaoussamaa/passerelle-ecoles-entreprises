<?php

namespace App\Http\Controllers;

use App\Models\VersionConvention;
use App\Services\ConventionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Actions des quatre parties sur une version de convention. La partie du
 * connecté est résolue depuis la mission : un tiers obtient 404 (cloisonnement),
 * une partie hors de son tour obtient une erreur métier explicite.
 */
class ConventionController extends Controller
{
    public function __construct(private readonly ConventionService $conventions) {}

    /** Version + rôle de partie du connecté, ou 404. */
    private function versionEtPartie(int $id): array
    {
        $version = VersionConvention::with('mission.etudiant.promotion.formation', 'mission.entreprise')->findOrFail($id);
        $role = $this->conventions->partieDe($version->mission, auth()->user());
        abort_if($role === null, 404);

        return [$version, $role];
    }

    public function pdf(int $id): StreamedResponse
    {
        [$version] = $this->versionEtPartie($id);
        abort_unless(Storage::exists($version->fichier_pdf), 404);

        return Storage::download($version->fichier_pdf,
            'convention-mission-'.$version->mission_id.'-v'.$version->numero.'.pdf');
    }

    /** RG-33 : chacun valide à son tour, dans l'ordre imposé. */
    public function valider(int $id): RedirectResponse
    {
        [$version, $role] = $this->versionEtPartie($id);

        if ($version->actionAttendueDe($role) !== 'valider') {
            return back()->withErrors(['convention' => 'Cette version n\'attend pas votre validation (circuit séquentiel).']);
        }
        $this->conventions->valider($version, auth()->user(), $role);

        return back()->with('succes', 'Validation enregistrée.');
    }

    /** RG-34 : refus motivé par une partie du circuit → correction (nouvelle version). */
    public function refuser(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'min:5']]);
        [$version, $role] = $this->versionEtPartie($id);

        $peutRefuser = $version->actionAttendueDe($role) !== null;          // son tour de valider, ou approbation ouverte
        if (! $peutRefuser) {
            return back()->withErrors(['convention' => 'Aucune action n\'est attendue de votre part sur cette version.']);
        }
        $this->conventions->refuser($version, auth()->user(), $role, $donnees['motif']);

        return back()->with('succes', 'Refus enregistré : le responsable corrigera et générera une nouvelle version.');
    }

    /** RG-35 : approbation en ordre libre après validation complète. */
    public function approuver(int $id): RedirectResponse
    {
        [$version, $role] = $this->versionEtPartie($id);

        if ($version->actionAttendueDe($role) !== 'approuver') {
            return back()->withErrors(['convention' => 'Cette version n\'est pas ouverte à votre approbation.']);
        }
        $this->conventions->approuver($version, auth()->user(), $role);

        return back()->with('succes', 'Approbation consignée (auteur, horodatage et empreinte tracés).');
    }

    /** RG-37 : annulation d'une version approuvée, réservée au responsable. */
    public function annuler(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'min:5']]);
        [$version, $role] = $this->versionEtPartie($id);

        if ($role !== 'responsable' || $version->statut !== 'approuvee') {
            return back()->withErrors(['convention' => 'Seule une convention approuvée peut être annulée, par le responsable.']);
        }
        $this->conventions->annuler($version, auth()->user(), $donnees['motif']);

        return back()->with('succes', 'Convention annulée : générez une nouvelle version, soumise au circuit complet.');
    }
}
