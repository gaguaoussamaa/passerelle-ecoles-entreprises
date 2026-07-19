<?php

namespace App\Http\Controllers\Ecole;

use App\Models\Domaine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormationController extends ControleurEcole
{
    public function index(): View
    {
        return view('ecole.formations.index', [
            'formations' => $this->etablissement()->formations()->with('domaines')->withCount('promotions')->orderBy('intitule')->get(),
            'domaines' => Domaine::orderBy('libelle')->get(),
        ]);
    }

    public function creer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'intitule' => ['required', 'string', 'max:150'],
            'niveau' => ['required', 'in:Bac+2,Bac+3,Bac+5'],
            'type_mission' => ['required', 'in:stage,alternance,les_deux'],
            'domaines' => ['required', 'array', 'min:1'],          // RG-09
            'domaines.*' => ['exists:domaines,id'],
        ]);

        $formation = $this->etablissement()->formations()->create($donnees);
        $formation->domaines()->sync($donnees['domaines']);

        return back()->with('statut', "Formation « {$formation->intitule} » créée.");
    }

    /** RG-10 : une formation référencée s'archive, elle ne se supprime pas. */
    public function archiver(int $id): RedirectResponse
    {
        $formation = $this->etablissement()->formations()->findOrFail($id);
        $formation->update(['archivee' => ! $formation->archivee]);

        return back()->with('statut', $formation->archivee ? 'Formation archivée.' : 'Formation réactivée.');
    }
}
