<?php

namespace App\Http\Controllers\Entreprise;

use App\Http\Controllers\Controller;
use App\Models\Diffusion;
use App\Models\Domaine;
use App\Models\Offre;
use App\Models\Partenariat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OffreController extends Controller
{
    /** Cloisonnement RG-07 : une entreprise n'atteint que ses propres offres. */
    private function mesOffres(): Builder
    {
        return Offre::where('entreprise_id', auth()->id());
    }

    private function partenariatsActifs()
    {
        return Partenariat::with('etablissement')
            ->where('entreprise_id', auth()->id())->where('statut', 'actif')->get();
    }

    public function index(): View
    {
        return view('entreprise.offres.index', [
            'offres' => $this->mesOffres()->with(['domaine', 'diffusions.etablissement', 'promotions'])->latest()->get(),
            'partenariats' => $this->partenariatsActifs(),
            'domaines' => Domaine::orderBy('libelle')->get(),
        ]);
    }

    public function creer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'intitule' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'type' => ['required', 'in:stage,alternance'],
            'niveau' => ['required', 'in:Bac+2,Bac+3,Bac+5'],
            'domaine_id' => ['required', 'exists:domaines,id'],
            'lieu' => ['required', 'string', 'max:100'],
            'date_debut_prevue' => ['required', 'date'],
            'date_fin_prevue' => ['required', 'date', 'after_or_equal:date_debut_prevue'],
            'nb_postes' => ['required', 'integer', 'min:1', 'max:50'],
            'etablissements' => ['required', 'array', 'min:1'],
        ]);

        // RG-16 : diffusion réservée aux écoles réellement partenaires (actives)
        $ecoles = $this->partenariatsActifs()->pluck('etablissement_id')
            ->intersect(collect($donnees['etablissements'])->map(fn ($v) => (int) $v));

        if ($ecoles->isEmpty()) {
            return back()->withErrors(['etablissements' => "Sélectionnez au moins une école partenaire active (RG-16)."])->withInput();
        }

        DB::transaction(function () use ($donnees, $ecoles) {
            $offre = Offre::create([...$donnees, 'entreprise_id' => auth()->id()]);
            foreach ($ecoles as $etabId) {
                Diffusion::create(['offre_id' => $offre->id, 'etablissement_id' => $etabId]);
            }
        });

        return back()->with('statut', "Offre publiée vers {$ecoles->count()} école(s) — en attente de modération.");
    }

    /** RG-20 : retrait tant qu'aucune école n'a validé ; les diffusions deviennent caduques. */
    public function retirer(int $id): RedirectResponse
    {
        $offre = $this->mesOffres()->findOrFail($id);

        if (! $offre->modifiable()) {
            return back()->withErrors(['retrait' => "Retrait impossible : une école a déjà validé cette offre (RG-20). Vous pouvez seulement la clôturer."]);
        }

        DB::transaction(function () use ($offre) {
            $offre->update(['statut' => 'retiree']);
            $offre->diffusions()->update(['statut' => 'caduque']);
        });

        return back()->with('statut', 'Offre retirée ; ses diffusions sont caduques.');
    }

    public function cloturer(int $id): RedirectResponse
    {
        $this->mesOffres()->findOrFail($id)->update(['statut' => 'pourvue']);

        return back()->with('statut', 'Offre clôturée (pourvue).');
    }
}
