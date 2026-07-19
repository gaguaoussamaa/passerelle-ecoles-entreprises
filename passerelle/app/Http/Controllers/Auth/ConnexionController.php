<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Compte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    public function afficher(): View
    {
        return view('auth.connexion');
    }

    public function connecter(Request $request): RedirectResponse
    {
        $identifiants = $request->validate([
            'email' => ['required', 'email'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        $compte = Compte::where('email', $identifiants['email'])->first();

        // RG-05 / ENF-04 : accès refusé selon le statut, avant toute session.
        if ($compte && ! $compte->accesAutorise()) {
            return back()->withErrors([
                'email' => "Ce compte n'a pas accès à la plateforme (compte non activé, désactivé ou statut de scolarité sans accès).",
            ])->onlyInput('email');
        }

        if (! Auth::attempt(['email' => $identifiants['email'], 'password' => $identifiants['mot_de_passe']])) {
            return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('tableau-de-bord'));
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion');
    }
}
