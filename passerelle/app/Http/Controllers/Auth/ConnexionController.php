<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    /** Limitation des tentatives (OWASP A07 : anti brute force / bourrage d'identifiants). */
    private const MAX_TENTATIVES = 5;
    private const FENETRE_SECONDES = 60;

    /** Message unique et générique : ne révèle jamais si l'e-mail existe (anti-énumération). */
    private const MESSAGE_ECHEC = 'Identifiants incorrects.';

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

        $cle = $this->cleLimitation($identifiants['email'], $request);

        // Trop de tentatives : on bloque avant toute vérification (protection brute force).
        if (RateLimiter::tooManyAttempts($cle, self::MAX_TENTATIVES)) {
            $secondes = RateLimiter::availableIn($cle);
            Log::warning('Connexion : verrouillage temporaire', ['ip' => $request->ip(), 'secondes' => $secondes]);

            return back()->withErrors([
                'email' => "Trop de tentatives de connexion. Réessayez dans {$secondes} secondes.",
            ])->onlyInput('email');
        }

        // Un seul message pour e-mail inconnu ET mot de passe faux (anti-énumération, OWASP A07).
        if (! Auth::attempt(['email' => $identifiants['email'], 'password' => $identifiants['mot_de_passe']])) {
            RateLimiter::hit($cle, self::FENETRE_SECONDES);

            return back()->withErrors(['email' => self::MESSAGE_ECHEC])->onlyInput('email');
        }

        // Identité prouvée : on peut alors refuser un accès révoqué (RG-05 / ENF-04) sans
        // fuite d'énumération — ce message n'atteint que qui connaît déjà le mot de passe.
        if (! $request->user()->accesAutorise()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($cle, self::FENETRE_SECONDES);

            return back()->withErrors([
                'email' => "Ce compte n'a pas accès à la plateforme (compte non activé, désactivé ou statut de scolarité sans accès).",
            ])->onlyInput('email');
        }

        RateLimiter::clear($cle);
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

    /** Clé de limitation par couple e-mail + IP (recommandation Laravel/OWASP). */
    private function cleLimitation(string $email, Request $request): string
    {
        return 'connexion|'.Str::lower($email).'|'.$request->ip();
    }
}
