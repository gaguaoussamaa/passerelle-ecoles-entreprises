<?php

namespace App\Http\Controllers\Entreprise;

use App\Http\Controllers\Controller;
use App\Mail\CandidatureStatutMail;
use App\Models\Candidature;
use App\Models\JournalAudit;
use App\Models\Offre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Candidatures reçues sur les offres de l'entreprise connectée (UC-07). */
class CandidatureController extends Controller
{
    private function recues(): Builder
    {
        return Candidature::whereHas('offre', fn ($q) => $q->where('entreprise_id', auth()->id()));
    }

    public function index(): View
    {
        return view('entreprise.candidatures.index', [
            'offres' => Offre::where('entreprise_id', auth()->id())
                ->whereHas('candidatures')
                ->with(['candidatures' => fn ($q) => $q->latest(),
                    'candidatures.etudiant.promotion.formation.etablissement'])
                ->latest()->get(),
        ]);
    }

    /** TU-04 : évolution depuis un état d'examen seulement ; étudiant notifié (RG-23). */
    public function changerStatut(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate([
            'statut' => ['required', 'in:preselectionnee,entretien,retenue,refusee'],
        ]);
        $candidature = $this->recues()->whereIn('statut', Candidature::EN_EXAMEN)->findOrFail($id);

        $candidature->update(['statut' => $donnees['statut']]);
        JournalAudit::tracer('candidature_'.$donnees['statut'], 'candidature', $candidature->id, auth()->id());
        Mail::to($candidature->etudiant->compte->email)
            ->send(new CandidatureStatutMail($candidature));                // RG-23

        return back()->with('succes', 'Statut mis à jour — le candidat est notifié.');
    }

    /** CV déposé (copie figée RG-48), réservé à l'entreprise destinataire. */
    public function cv(int $id): StreamedResponse
    {
        $candidature = $this->recues()->findOrFail($id);                    // cloisonnement
        abort_unless(Storage::exists($candidature->cv_depose), 404);

        return Storage::download($candidature->cv_depose, 'cv-candidature-'.$candidature->id.'.pdf');
    }
}
