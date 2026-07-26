<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function afficher(string $jeton): View
    {
        $invitation = $this->invitations->valider($jeton);

        return view('auth.activation', [
            'jeton' => $jeton,
            'invitation' => $invitation,
        ]);
    }

    public function activer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'jeton' => ['required', 'string'],
            // RG-04 — mot de passe robuste : ≥ 12 caractères avec lettres ET chiffres.
            // Combiné à la limitation des tentatives (ConnexionController), cela dépasse le
            // seuil CNIL 2022 de 50 bits d'entropie applicable « avec mesure de restriction
            // d'accès » (délib. 2022-100) ; la vérification anti-fuite HIBP reste pour la prod.
            'mot_de_passe' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        $invitation = $this->invitations->valider($donnees['jeton']);

        if (! $invitation) {
            return redirect()->route('connexion')->withErrors([
                'email' => "Ce lien d'activation est invalide, expiré ou déjà utilisé. Demandez un renvoi d'invitation à votre établissement.",
            ]);
        }

        $this->invitations->activer($invitation, $donnees['mot_de_passe']);

        return redirect()->route('connexion')->with('statut', 'Votre compte est activé : vous pouvez vous connecter.');
    }
}
