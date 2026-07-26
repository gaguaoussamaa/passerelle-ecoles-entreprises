<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP (durcissement OWASP A05 « Security Misconfiguration ») :
 *  - X-Frame-Options: DENY           → anti-clickjacking (page non « framable ») ;
 *  - X-Content-Type-Options: nosniff → pas de MIME-sniffing du navigateur ;
 *  - Referrer-Policy: same-origin    → le référent n'est pas divulgué hors du site.
 *
 * La CSP n'est pas posée ici : l'application utilise quelques `onsubmit`/`style`
 * en ligne ; une CSP stricte les casserait. Elle est documentée comme évolution
 * (retrait des handlers/styles en ligne au préalable).
 */
class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $reponse->headers->set('X-Frame-Options', 'DENY');
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('Referrer-Policy', 'same-origin');

        return $reponse;
    }
}
