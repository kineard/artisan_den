<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $rolePermissions = [
        'admin' => ['*'],
        'manager' => ['kpi_write', 'inventory_write', 'timeclock_manager', 'employee_self_service'],
        'employee' => ['employee_self_service'],
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $role = strtolower((string)$request->session()->get('user_role', ''));
        if (!in_array($role, ['admin', 'manager', 'employee'], true)) {
            abort(403, 'Permission denied for this action.');
        }

        $allowed = $this->rolePermissions[$role] ?? [];
        $can = in_array('*', $allowed, true) || in_array($permission, $allowed, true);
        if (!$can) {
            abort(403, 'Permission denied for this action.');
        }

        return $next($request);
    }
}
