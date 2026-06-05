<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Conta desativada — desconecta e redireciona
        if (! $user->is_active) {
            ActivityLog::record('auth.blocked', 'Acesso bloqueado: conta desativada');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Conta desativada. Entre em contato com o administrador.']);
        }

        // Perfil "usuario" não tem acesso ao painel web
        if ($user->isUsuario()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Seu perfil permite apenas o uso da extensão GovCert. O acesso ao painel é restrito a auditores e administradores.']);
        }

        return $next($request);
    }
}
