<?php

namespace App\Http\Controllers;

use App\Models\KpiDaily;
use App\Services\AppContextResolver;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiController extends Controller
{
    public function index(Request $request): View
    {
        [$tenant, $location, $locations] = $this->resolveContext($request);
        $entryDate = (string)($request->query('date', now()->toDateString()));

        $kpi = KpiDaily::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->where('entry_date', $entryDate)
            ->first();

        $recentRows = KpiDaily::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->orderByDesc('entry_date')
            ->limit(14)
            ->get();

        return view('kpi.index', [
            'tenant' => $tenant,
            'location' => $location,
            'locations' => $locations,
            'entryDate' => $entryDate,
            'kpi' => $kpi,
            'recentRows' => $recentRows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$tenant, $location] = $this->resolveContext($request);

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'bank_balance' => ['required', 'string'],
            'safe_balance' => ['required', 'string'],
            'sales_today' => ['required', 'string'],
            'cogs_today' => ['required', 'string'],
            'labor_today' => ['required', 'string'],
            'avg_daily_overhead' => ['required', 'string'],
        ]);

        $payload = [
            'bank_balance_cents' => $this->moneyToCents($validated['bank_balance']),
            'safe_balance_cents' => $this->moneyToCents($validated['safe_balance']),
            'sales_today_cents' => $this->moneyToCents($validated['sales_today']),
            'cogs_today_cents' => $this->moneyToCents($validated['cogs_today']),
            'labor_today_cents' => $this->moneyToCents($validated['labor_today']),
            'avg_daily_overhead_cents' => $this->moneyToCents($validated['avg_daily_overhead']),
        ];

        KpiDaily::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'entry_date' => $validated['entry_date'],
            ],
            $payload
        );
        app(AuditLogger::class)->log($request, 'kpi.saved', [
            'entry_date' => $validated['entry_date'],
            'location_id' => $location->id,
        ]);

        return redirect()
            ->route('kpi.index', ['location_id' => $location->id, 'date' => $validated['entry_date']])
            ->with('status', 'KPI saved.');
    }

    /**
     * @return array{0: Tenant, 1: Location, 2?: \Illuminate\Support\Collection<int, Location>}
     */
    private function resolveContext(Request $request): array
    {
        $tenant = $request->attributes->get('tenant');
        $location = $request->attributes->get('location');
        $locations = $request->attributes->get('locations');
        if ($tenant !== null && $location !== null && $locations !== null) {
            return [$tenant, $location, $locations];
        }

        return app(AppContextResolver::class)->resolve($request);
    }

    private function moneyToCents(string $raw): int
    {
        $normalized = str_replace([',', '$', ' '], '', trim($raw));
        if ($normalized === '') {
            return 0;
        }
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $normalized)) {
            abort(422, 'Invalid money value: ' . $raw);
        }

        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '0');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        $cents = ((int)$whole * 100) + (int)$fraction;
        return $negative ? -$cents : $cents;
    }
}

