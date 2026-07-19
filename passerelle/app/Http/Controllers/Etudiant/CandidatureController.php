<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Mail\CandidatureEntrepriseMail;
use App\Models\Candidature;
use App\Models\JournalAudit;
use App\Models\Mission;
use App\Models\Offre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Candidatures de l'étudiant connecté (UC-06/07) : CV, dépôt, retrait, décision. */
class CandidatureController extends Controller
{
    private function miennes(): Builder
    {
        return Candidature::where('etudiant_id', auth()->id());
    }

    public function index(): View
    {
        return view('etudiant.candidatures.index', [
            'etudiant' => auth()->user()->etudiant,
            'candidatures' => $this->miennes()->with('offre.entreprise', 'mission')->latest()->get(),
        ]);
    }

    /** Dépôt/remplacement du CV de profil — sans effet sur les candidatures émises (RG-48). */
    public function deposerCv(Request $request): RedirectResponse
    {
        $request->validate(['cv' => ['required', 'file', 'mimes:pdf', 'max:2048']]);
        $etudiant = auth()->user()->etudiant;

        $chemin = $request->file('cv')->storeAs('cv/profils', $etudiant->compte_id.'.pdf');
        $etudiant->update(['cv_profil' => $chemin]);

        return back()->with('succes', 'CV enregistré.');
    }

    public function telechargerCv(): StreamedResponse
    {
        $etudiant = auth()->user()->etudiant;
        abort_unless($etudiant->cv_profil && Storage::exists($etudiant->cv_profil), 404);

        return Storage::download($etudiant->cv_profil, 'cv-profil.pdf');
    }

    /** TV-12 : la candidature émise conserve le CV déposé à l'origine. */
    public function cvCandidature(int $id): StreamedResponse
    {
        $candidature = $this->miennes()->findOrFail($id);
        abort_unless(Storage::exists($candidature->cv_depose), 404);

        return Storage::download($candidature->cv_depose, 'cv-candidature-'.$candidature->id.'.pdf');
    }

    /** UC-06 : candidater = message + copie physique du CV (RG-48), une seule fois (RG-22). */
    public function candidater(Request $request, int $offreId): RedirectResponse
    {
        $etudiant = auth()->user()->etudiant;
        $offre = Offre::visiblesPar($etudiant)->findOrFail($offreId);       // cloisonnement
        $donnees = $request->validate(['message' => ['required', 'string', 'min:10', 'max:2000']]);

        if (! $etudiant->cv_profil || ! Storage::exists($etudiant->cv_profil)) {
            return back()->withErrors(['cv' => 'Déposez d\'abord votre CV depuis la page « Mes candidatures ».']);
        }
        if ($this->miennes()->where('offre_id', $offre->id)->exists()) {    // RG-22 : contrôle préalable
            return back()->withErrors(['candidature' => 'Vous avez déjà candidaté à cette offre.']);
        }

        $copie = 'cv/candidatures/'.$etudiant->compte_id.'-'.$offre->id.'.pdf';
        Storage::copy($etudiant->cv_profil, $copie);                        // RG-48 : CV figé au dépôt

        try {
            DB::transaction(function () use ($etudiant, $offre, $donnees, $copie) {
                $candidature = Candidature::create([
                    'etudiant_id' => $etudiant->compte_id, 'offre_id' => $offre->id,
                    'message' => $donnees['message'], 'cv_depose' => $copie,
                ]);
                JournalAudit::tracer('candidature_deposee', 'candidature', $candidature->id, auth()->id());
                Mail::to($offre->entreprise->compte->email)                 // TI-06 : notification entreprise
                    ->send(new CandidatureEntrepriseMail($candidature, 'deposee'));
            });
        } catch (UniqueConstraintViolationException) {                      // RG-22 garanti par la base (TU-17)
            Storage::delete($copie);

            return back()->withErrors(['candidature' => 'Vous avez déjà candidaté à cette offre.']);
        }

        return redirect()->route('etudiant.candidatures')->with('succes', 'Candidature envoyée.');
    }

    /** RG-24 : retrait possible tant que la candidature n'est pas « retenue ». */
    public function retirer(int $id): RedirectResponse
    {
        $candidature = $this->miennes()->findOrFail($id);

        if (! $candidature->retirable()) {
            return back()->withErrors(['retrait' => 'Cette candidature ne peut plus être retirée.']);
        }

        $candidature->update(['statut' => 'retiree']);
        JournalAudit::tracer('candidature_retiree', 'candidature', $candidature->id, auth()->id());

        return back()->with('succes', 'Candidature retirée.');
    }

    /** UC-07 : la confirmation crée la mission (RG-26) et retire les autres candidatures actives (RG-25). */
    public function confirmer(int $id): RedirectResponse
    {
        $candidature = $this->miennes()->where('statut', 'retenue')->with('offre')->findOrFail($id);

        DB::transaction(function () use ($candidature) {
            $candidature->update(['statut' => 'confirmee']);

            $offre = $candidature->offre;
            $mission = Mission::create([
                'etudiant_id' => $candidature->etudiant_id,
                'entreprise_id' => $offre->entreprise_id,
                'candidature_id' => $candidature->id,                       // origine chemin A
                'type' => $offre->type,
                'date_debut' => $offre->date_debut_prevue,
                'date_fin' => $offre->date_fin_prevue,
            ]);
            JournalAudit::tracer('candidature_confirmee', 'candidature', $candidature->id, auth()->id());
            JournalAudit::tracer('mission_creee', 'mission', $mission->id, auth()->id());

            $this->miennes()->whereKeyNot($candidature->id)                 // RG-25 : retrait automatique tracé
                ->whereIn('statut', Candidature::ACTIFS)->get()
                ->each(function (Candidature $autre) {
                    $autre->update(['statut' => 'retiree']);
                    JournalAudit::tracer('candidature_retiree_auto', 'candidature', $autre->id);
                });

            Mail::to($offre->entreprise->compte->email)
                ->send(new CandidatureEntrepriseMail($candidature, 'confirmee'));
        });

        return back()->with('succes', 'Engagement confirmé — la mission est créée (en montage).');
    }

    /** TV-14 : déclinaison d'une candidature retenue — l'offre reste ouverte. */
    public function decliner(int $id): RedirectResponse
    {
        $candidature = $this->miennes()->where('statut', 'retenue')->with('offre')->findOrFail($id);

        $candidature->update(['statut' => 'declinee']);
        JournalAudit::tracer('candidature_declinee', 'candidature', $candidature->id, auth()->id());
        Mail::to($candidature->offre->entreprise->compte->email)
            ->send(new CandidatureEntrepriseMail($candidature, 'declinee'));

        return back()->with('succes', 'Candidature déclinée.');
    }
}
