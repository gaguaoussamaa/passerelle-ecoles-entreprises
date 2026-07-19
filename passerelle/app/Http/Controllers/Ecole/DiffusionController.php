<?php

namespace App\Http\Controllers\Ecole;

use App\Models\Diffusion;
use App\Models\JournalAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Modération des offres reçues (RG-17..19) — indépendante par école. */
class DiffusionController extends ControleurEcole
{
    private function diffusions(): Builder
    {
        return Diffusion::where('etablissement_id', $this->etablissement()->id);
    }

    public function index(): View
    {
        return view('ecole.offres.index', [
            'aModerer' => $this->diffusions()->where('statut', 'soumise')
                ->whereHas('offre', fn ($q) => $q->where('statut', 'publiee'))
                ->with('offre.entreprise', 'offre.domaine')->get(),
            'traitees' => $this->diffusions()->whereIn('statut', ['validee', 'refusee', 'caduque'])
                ->with('offre.entreprise', 'offre.promotions')->latest('updated_at')->get(),
            'promotions' => $this->promotionsEtablissement()->where('archivee', false)->with('formation')->get(),
        ]);
    }

    /** RG-19 : validation = affectation à au moins une promotion de l'école. */
    public function valider(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate(['promotions' => ['required', 'array', 'min:1']]);
        $diffusion = $this->diffusions()->where('statut', 'soumise')->findOrFail($id);

        $promotions = $this->promotionsEtablissement()
            ->whereIn('id', $donnees['promotions'])->pluck('id');           // cloisonnement TI-05

        if ($promotions->isEmpty()) {
            return back()->withErrors(['promotions' => 'Sélectionnez au moins une promotion de votre établissement.']);
        }

        DB::transaction(function () use ($diffusion, $promotions) {
            $diffusion->update(['statut' => 'validee']);
            $diffusion->offre->promotions()->syncWithoutDetaching($promotions);
            JournalAudit::tracer('offre_validee', 'diffusion', $diffusion->id, auth()->id());
        });

        return back()->with('statut', "Offre validée et visible de {$promotions->count()} promotion(s).");
    }

    /** RG-18 : refus motivé, sans effet sur les autres écoles. */
    public function refuser(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate(['motif_refus' => ['required', 'string', 'min:5']]);
        $diffusion = $this->diffusions()->where('statut', 'soumise')->findOrFail($id);

        $diffusion->update(['statut' => 'refusee', 'motif_refus' => $donnees['motif_refus']]);
        JournalAudit::tracer('offre_refusee', 'diffusion', $diffusion->id, auth()->id(), $donnees['motif_refus']);

        return back()->with('statut', 'Offre refusée pour votre établissement (motif transmis).');
    }
}
