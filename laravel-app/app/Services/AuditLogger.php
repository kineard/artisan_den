<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param array<string, mixed> $meta
     */
    public function log(Request $request, string $action, array $meta = []): void
    {
        $tenant = $request->attributes->get('tenant');
        $location = $request->attributes->get('location');
        $role = strtolower((string)($request->session()->get('user_role', 'manager')));
        $actor = (string)($request->session()->get('user_name', ucfirst($role) . ' User'));

        AuditLog::query()->create([
            'tenant_id' => $tenant?->id,
            'location_id' => $location?->id,
            'actor_name' => $actor,
            'actor_role' => $role,
            'action' => $action,
            'details_json' => json_encode($meta, JSON_UNESCAPED_SLASHES),
        ]);
    }
}

