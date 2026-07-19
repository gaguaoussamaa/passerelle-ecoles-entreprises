<?php

namespace App\Http\Controllers\Ecole;

use App\Models\Compte;
use App\Models\TuteurPedagogique;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TuteurController extends ControleurEcole
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function index(): View
    {
        return view('ecole.tuteurs.index', [
            'tuteurs' => $this->etablissement()->hasMany(TuteurPedagogique::class, 'etablissement_id')
                ->getQuery()->with(['compte', 'formations'])->orderBy('nom')->get(),
            'formations' => $this->etablissement()->formations()->where('archivee', false)->orderBy('intitule')->get(),
        ]);
    }

    public function creer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:comptes,email'],  // RG-03
            'formations' => ['array'],
            'formations.*' => ['integer'],
        ]);

        // cloisonnement : rattachements limités aux formations de l'établissement
        $formationsValides = $this->etablissement()->formations()
            ->whereIn('id', $donnees['formations'] ?? [])->pluck('id');

        $compte = DB::transaction(function () use ($donnees, $formationsValides) {
            $compte = Compte::create(['email' => $donnees['email'], 'role' => 'tuteur_pedagogique']);
            $tuteur = TuteurPedagogique::create([
                'compte_id' => $compte->id,
                'etablissement_id' => $this->etablissement()->id,
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'],
            ]);
            $tuteur->formations()->sync($formationsValides);

            return $compte;
        });

        $this->invitations->inviter($compte);

        return back()->with('statut', "Tuteur créé et invitation envoyée à {$donnees['email']}.");
    }
}
