<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSsoAuthenticated
{
    /**
     * Pastikan pengguna memiliki sesi SSO aktif sebelum mengakses dashboard/chat.
     * Request browser dialihkan (302) ke halaman login, sedangkan request JSON/AJAX menerima 401.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('sso_user')) {
            if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
