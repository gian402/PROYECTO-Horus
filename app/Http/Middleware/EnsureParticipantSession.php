<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Public\ParticipantPortalController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege las páginas del portal del participante (después de identificarse
 * con DNI + código). No usa el guard de autenticación de Laravel: el
 * participante no es un App\Models\User, solo una marca en la sesión.
 */
class EnsureParticipantSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has(ParticipantPortalController::SESSION_KEY)) {
            return redirect()->route('portal.login');
        }

        return $next($request);
    }
}
