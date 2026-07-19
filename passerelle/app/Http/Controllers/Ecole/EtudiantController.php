<?php

namespace App\Http\Controllers\Ecole;

use App\Models\Compte;
use App\Models\Etudiant;
use App\Models\JournalAudit;
use App\Services\ImportEtudiantsService;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EtudiantController extends ControleurEcole
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly ImportEtudiantsService $import,
    ) {}

    public function index(Request $request): View
    {
        $etudiants = $this->etudiantsEtablissement()
            ->with(['compte', 'promotion.formation'])
            ->when($request->integer('promotion'), fn ($q, $p) => $q->where('promotion_id', $p))
            ->when($request->string('statut')->toString(), fn ($q, $s) => $q->where('statut_scolarite', $s))
            ->orderBy('nom')->get();

        return view('ecole.etudiants.index', [
            'etudiants' => $etudiants,
            'promotions' => $this->promotionsEtablissement()->where('archivee', false)->with('formation')->get(),
            'filtres' => ['promotion' => $request->integer('promotion'), 'statut' => $request->string('statut')->toString()],
        ]);
    }

    public function creer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:comptes,email'],   // RG-03
            'promotion_id' => ['required', 'integer'],
        ]);

        $promotion = $this->promotionsEtablissement()->findOrFail($donnees['promotion_id']); // cloisonnement

        $compte = DB::transaction(function () use ($donnees, $promotion) {
            $compte = Compte::create(['email' => $donnees['email'], 'role' => 'etudiant']);
            Etudiant::create([
                'compte_id' => $compte->id,
                'promotion_id' => $promotion->id,
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'],
                'statut_scolarite' => 'invite',
            ]);

            return $compte;
        });

        $this->invitations->inviter($compte);   // EF-08

        return back()->with('statut', "Étudiant créé et invitation envoyée à {$donnees['email']}.");
    }

    public function fiche(int $compteId): View
    {
        return view('ecole.etudiants.fiche', [
            'etudiant' => $this->etudiantsEtablissement()->with(['compte', 'promotion.formation'])->findOrFail($compteId),
            'promotions' => $this->promotionsEtablissement()->where('archivee', false)->with('formation')->get(),
            'statuts' => Etudiant::STATUTS,
            'dossierVierge' => $this->dossierVierge($compteId),
        ]);
    }

    /** EF-33 : statut de scolarité réversible et tracé — l'accès en découle (RG-05). */
    public function changerStatut(Request $request, int $compteId): RedirectResponse
    {
        $donnees = $request->validate(['statut' => ['required', 'in:'.implode(',', Etudiant::STATUTS)]]);
        $etudiant = $this->etudiantsEtablissement()->findOrFail($compteId);

        $ancien = $etudiant->statut_scolarite;
        $etudiant->update(['statut_scolarite' => $donnees['statut']]);
        JournalAudit::tracer('changement_statut_scolarite', 'compte', $compteId,
            auth()->id(), "{$ancien} → {$donnees['statut']}");

        return back()->with('statut', "Statut modifié : {$ancien} → {$donnees['statut']} (tracé, réversible).");
    }

    public function changerPromotion(Request $request, int $compteId): RedirectResponse
    {
        $donnees = $request->validate(['promotion_id' => ['required', 'integer']]);
        $etudiant = $this->etudiantsEtablissement()->findOrFail($compteId);
        $promotion = $this->promotionsEtablissement()->findOrFail($donnees['promotion_id']); // cloisonnement

        $etudiant->update(['promotion_id' => $promotion->id]);
        JournalAudit::tracer('changement_promotion', 'compte', $compteId, auth()->id());

        return back()->with('statut', 'Rattachement de promotion mis à jour.');
    }

    /** RG-02 : le renvoi invalide l'ancien jeton. */
    public function renvoyerInvitation(int $compteId): RedirectResponse
    {
        $etudiant = $this->etudiantsEtablissement()->with('compte')->findOrFail($compteId);
        $this->invitations->inviter($etudiant->compte);

        return back()->with('statut', "Nouvelle invitation envoyée à {$etudiant->compte->email} (l'ancien lien est invalidé).");
    }

    /** RG-15 : suppression physique réservée aux dossiers vierges. */
    public function supprimer(int $compteId): RedirectResponse
    {
        $etudiant = $this->etudiantsEtablissement()->with('compte')->findOrFail($compteId);

        if (! $this->dossierVierge($compteId)) {
            return back()->withErrors(['suppression' =>
                "Suppression impossible : le dossier n'est pas vierge (candidatures, déclarations ou missions). Utilisez un changement de statut (diplômé / sorti)."]);
        }

        DB::transaction(function () use ($etudiant) {
            $etudiant->compte->invitations()->delete();
            $etudiant->delete();
            $etudiant->compte->delete();
        });

        return redirect()->route('ecole.etudiants')->with('statut', 'Dossier vierge supprimé définitivement.');
    }

    private function dossierVierge(int $compteId): bool
    {
        return DB::table('candidatures')->where('etudiant_id', $compteId)->doesntExist()
            && DB::table('declarations')->where('etudiant_id', $compteId)->doesntExist()
            && DB::table('missions')->where('etudiant_id', $compteId)->doesntExist();
    }

    // ------------------------------------------------------------ Import CSV
    public function importFormulaire(): View
    {
        return view('ecole.etudiants.import', ['rapport' => session('rapport')]);
    }

    public function importTraiter(Request $request): RedirectResponse
    {
        $request->validate(['fichier' => ['required', 'file', 'mimes:csv,txt', 'max:1024']]);

        $rapport = $this->import->importer($request->file('fichier'), $this->etablissement());

        return redirect()->route('ecole.etudiants.import')
            ->with('rapport', $rapport)
            ->with('statut', "Import terminé : {$rapport['crees']} créé(s), {$rapport['rejets']} rejeté(s).");
    }

    public function importModele()
    {
        $exemple = "nom;prenom;email;promotion\nKaddouri;Sarah;sarah.kaddouri@exemple.fr;M2 Dev 2026-2027\n";

        return response($exemple, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-etudiants.csv"',
        ]);
    }
}
