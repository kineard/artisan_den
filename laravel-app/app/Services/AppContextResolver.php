<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AppContextResolver
{
    /**
     * @return array{0: Tenant, 1: Location, 2: Collection<int, Location>}
     */
    public function resolve(Request $request): array
    {
        $tenant = Tenant::query()->first();
        if ($tenant === null) {
            $tenant = Tenant::query()->create([
                'name' => 'Default Business',
                'slug' => 'default-business',
                'status' => 'active',
            ]);
        }

        $locations = Location::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($locations->isEmpty()) {
            $seedLocation = Location::query()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Main Location',
                'code' => 'MAIN',
                'is_active' => true,
            ]);
            $locations = collect([$seedLocation]);
        }

        $requestedLocationId = (int)($request->input('location_id', $request->query('location_id', 0)));
        if ($requestedLocationId <= 0) {
            $requestedLocationId = (int)$request->session()->get('preferred_location_id', 0);
        }

        $location = $locations->firstWhere('id', $requestedLocationId);
        if ($location === null) {
            // Prefer imported/business locations over scaffold "Main Location" when multiple exist.
            $nonMain = $locations->first(function (Location $loc): bool {
                return strcasecmp((string)$loc->name, 'Main Location') !== 0;
            });
            $location = $nonMain ?? $locations->first();
        }

        if ($location === null) {
            abort(500, 'No active location found.');
        }
        $request->session()->put('preferred_location_id', (int)$location->id);

        return [$tenant, $location, $locations];
    }
}

