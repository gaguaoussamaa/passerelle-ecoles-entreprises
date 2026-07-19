<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * RG-05 / ENF-04 revérifiées à chaque requête : un changement de statut
 * (étudiant « sorti », compte désactivé) coupe l'accès immédiatement,
 * y compris pour une session déjà ouverte.
 */
class VerifierAcces
{
    public function handle(Request $request, Closure $next): Response
    {
        $compte = $request->user();

        if ($compte && ! $compte->accesAutorise()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('connexion')->withErrors([
                'email' => "Votre accès à la plateforme a été révoqué.",
            ]);
        }

        return $next($request);
    }
}
