<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AppContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request, AppContextResolver $resolver): View
    {
        [$tenant, $location] = $resolver->resolve($request);
        $this->ensureDefaultUsers($tenant->id, $location->id);

        return view('auth.login');
    }

    public function login(Request $request, AppContextResolver $resolver): RedirectResponse
    {
        [$tenant, $location] = $resolver->resolve($request);
        $this->ensureDefaultUsers($tenant->id, $location->id);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', strtolower(trim((string)$validated['email'])))
            ->first();

        if ($user === null || !Hash::check((string)$validated['password'], (string)$user->password)) {
            return back()->withErrors(['login' => 'Invalid login credentials.'])->withInput();
        }

        $request->session()->put([
            'auth_user_id' => $user->id,
            'user_id' => $user->id,
            'user_role' => strtolower((string)$user->role),
            'user_name' => (string)$user->name,
            'preferred_location_id' => (int)$location->id,
        ]);

        return redirect()->route('kpi.index', ['location_id' => $location->id]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login');
    }

    private function ensureDefaultUsers(int $tenantId, int $locationId): void
    {
        $defaults = [
            [
                'name' => 'Admin User',
                'email' => 'admin@artisan.local',
                'role' => 'admin',
                'password' => 'admin1234',
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@artisan.local',
                'role' => 'manager',
                'password' => 'manager1234',
            ],
            [
                'name' => 'Employee User',
                'email' => 'employee@artisan.local',
                'role' => 'employee',
                'password' => 'employee1234',
            ],
        ];

        foreach ($defaults as $item) {
            User::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'email' => $item['email']],
                [
                    'location_id' => $locationId,
                    'name' => $item['name'],
                    'role' => $item['role'],
                    'password' => Hash::make($item['password']),
                ]
            );
        }
    }
}
