<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Declaration;
use App\Models\JournalAudit;
use App\Models\Mission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Déclaration d'une mission trouvée hors plateforme (UC-08, chemin B — RG-46). */
class DeclarationController extends Controller
{
    private function derniere(): ?Declaration
    {
        return Declaration::where('etudiant_id', auth()->id())->latest('id')->first();
    }

    /** Mission qui engage encore l'étudiant — bloque toute nouvelle déclaration. */
    private function missionEnCours(): ?Mission
    {
        return Mission::where('etudiant_id', auth()->id())
            ->whereIn('statut', Mission::EN_COURS)->first();
    }

    public function index(): View
    {
        return view('etudiant.declaration.index', [
            'declaration' => $this->derniere(),
            'missionEnCours' => $this->missionEnCours(),
        ]);
    }

    /** Dépôt (ou correction + re-soumission d'une déclaration refusée — RG-46). */
    public function soumettre(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'type_mission' => ['required', Rule::in(['stage', 'alternance'])],
            'date_debut_prevue' => ['required', 'date'],
            'date_fin_prevue' => ['required', 'date', 'after_or_equal:date_debut_prevue'],
            'entreprise_saisie' => ['required', 'string', 'max:150'],
            'siret_saisi' => ['nullable', 'digits:14'],
            'contact_nom' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:190'],
            'description' => ['required', 'string', 'min:20'],
        ]);

        if ($this->missionEnCours()) {
            return back()->withErrors(['declaration' => 'Une mission est déjà en cours : aucune nouvelle déclaration possible.']);
        }

        $derniere = $this->derniere();
        if ($derniere && $derniere->statut === 'refusee') {                 // correction re-soumise (TV-16)
            $derniere->update([...$donnees, 'statut' => 'soumise', 'motif_refus' => null]);
            JournalAudit::tracer('declaration_resoumise', 'declaration', $derniere->id, auth()->id());

            return back()->with('succes', 'Déclaration corrigée et soumise à nouveau.');
        }
        if ($derniere && in_array($derniere->statut, Declaration::EN_COURS, true)) {
            return back()->withErrors(['declaration' => 'Vous avez déjà une déclaration en cours (RG-46).']);
        }

        $declaration = Declaration::create([...$donnees, 'etudiant_id' => auth()->id()]);
        JournalAudit::tracer('declaration_soumise', 'declaration', $declaration->id, auth()->id());

        return back()->with('succes', 'Déclaration soumise : votre responsable va en examiner la recevabilité.');
    }

    /** RG-46 : retrait par l'étudiant tant que la déclaration n'est pas devenue recevable. */
    public function abandonner(): RedirectResponse
    {
        $declaration = $this->derniere();

        if (! $declaration || ! in_array($declaration->statut, Declaration::EN_COURS, true)) {
            return back()->withErrors(['declaration' => 'Aucune déclaration en cours à abandonner.']);
        }

        $declaration->update(['statut' => 'abandonnee']);
        JournalAudit::tracer('declaration_abandonnee', 'declaration', $declaration->id, auth()->id());

        return back()->with('succes', 'Déclaration abandonnée.');
    }
}
