<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Autorisation par rôle (RG-06) : usage `role:responsable,super_admin`. */
class VerifierRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $compte = $request->user();

        if (! $compte || ! in_array($compte->role, $roles, true)) {
            // Journal de sécurité (OWASP A09) : refus d'autorisation par rôle.
            Log::warning('Autorisation refusée (rôle)', [
                'compte_id' => $compte?->id,
                'role' => $compte?->role,
                'chemin' => $request->path(),
            ]);
            abort(403, "Action non autorisée pour votre rôle.");
        }

        return $next($request);
    }
}
