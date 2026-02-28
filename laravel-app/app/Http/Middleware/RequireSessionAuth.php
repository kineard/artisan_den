<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSessionAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int)$request->session()->get('auth_user_id', 0);
        $role = strtolower((string)$request->session()->get('user_role', ''));
        if ($userId <= 0 || !in_array($role, ['admin', 'manager', 'employee'], true)) {
            return redirect()->route('auth.login');
        }

        return $next($request);
    }
}
