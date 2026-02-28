<?php

namespace App\Http\Controllers;

use App\Models\TimePunchEvent;
use App\Models\TimeShift;
use App\Models\TimeclockEmployee;
use App\Services\AppContextResolver;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TimeclockController extends Controller
{
    public function index(Request $request): View
    {
        [$tenant, $location, $locations] = $this->resolveContext($request);

        $employees = TimeclockEmployee::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $openShifts = TimeShift::query()
            ->with('employee')
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->whereNull('clock_out_at')
            ->orderByDesc('clock_in_at')
            ->get();

        $todayPunches = TimePunchEvent::query()
            ->with('employee')
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->whereDate('event_at', now()->toDateString())
            ->orderByDesc('event_at')
            ->limit(20)
            ->get();

        return view('timeclock.index', [
            'tenant' => $tenant,
            'location' => $location,
            'locations' => $locations,
            'employees' => $employees,
            'openShifts' => $openShifts,
            'todayPunches' => $todayPunches,
        ]);
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        [$tenant, $location] = $this->resolveContext($request);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'role_name' => ['required', 'string', 'max:100'],
            'pin' => ['required', 'string', 'min:4', 'max:12'],
            'hourly_rate' => ['nullable', 'string'],
        ]);

        TimeclockEmployee::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'full_name' => trim((string)$validated['full_name']),
            'role_name' => trim((string)$validated['role_name']),
            'pin_hash' => Hash::make((string)$validated['pin']),
            'hourly_rate_cents' => $this->moneyToCents((string)($validated['hourly_rate'] ?? '0')),
            'is_active' => true,
        ]);
        app(AuditLogger::class)->log($request, 'timeclock.employee_created', [
            'location_id' => $location->id,
            'employee_name' => trim((string)$validated['full_name']),
            'role_name' => trim((string)$validated['role_name']),
        ]);

        return redirect()
            ->route('timeclock.index', ['location_id' => $location->id])
            ->with('status', 'Employee created.');
    }

    public function punch(Request $request): RedirectResponse
    {
        [$tenant, $location] = $this->resolveContext($request);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'pin' => ['required', 'string'],
            'punch_type' => ['required', 'in:in,out'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = TimeclockEmployee::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->where('id', (int)$validated['employee_id'])
            ->where('is_active', true)
            ->first();

        if ($employee === null || !Hash::check((string)$validated['pin'], (string)$employee->pin_hash)) {
            return redirect()
                ->route('timeclock.index', ['location_id' => $location->id])
                ->withErrors(['punch' => 'Invalid employee or PIN.']);
        }

        $openShift = TimeShift::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->where('timeclock_employee_id', $employee->id)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();

        if ($validated['punch_type'] === 'in') {
            if ($openShift !== null) {
                return redirect()
                    ->route('timeclock.index', ['location_id' => $location->id])
                    ->withErrors(['punch' => 'Employee is already clocked in.']);
            }

            $newShift = TimeShift::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'timeclock_employee_id' => $employee->id,
                'clock_in_at' => now(),
                'clock_in_source' => 'web',
                'clock_in_note' => $validated['note'] ?? null,
            ]);

            TimePunchEvent::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'timeclock_employee_id' => $employee->id,
                'time_shift_id' => $newShift->id,
                'event_type' => 'CLOCK_IN',
                'event_at' => now(),
                'source' => 'web',
                'note' => $validated['note'] ?? null,
            ]);
            app(AuditLogger::class)->log($request, 'timeclock.clock_in', [
                'location_id' => $location->id,
                'employee_id' => $employee->id,
                'shift_id' => $newShift->id,
            ]);

            return redirect()
                ->route('timeclock.index', ['location_id' => $location->id])
                ->with('status', 'Clock in recorded.');
        }

        // punch_type = out
        if ($openShift === null) {
            return redirect()
                ->route('timeclock.index', ['location_id' => $location->id])
                ->withErrors(['punch' => 'No open shift found for employee.']);
        }

        $openShift->update([
            'clock_out_at' => now(),
            'clock_out_source' => 'web',
            'clock_out_note' => $validated['note'] ?? null,
        ]);

        TimePunchEvent::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'timeclock_employee_id' => $employee->id,
            'time_shift_id' => $openShift->id,
            'event_type' => 'CLOCK_OUT',
            'event_at' => now(),
            'source' => 'web',
            'note' => $validated['note'] ?? null,
        ]);
        app(AuditLogger::class)->log($request, 'timeclock.clock_out', [
            'location_id' => $location->id,
            'employee_id' => $employee->id,
            'shift_id' => $openShift->id,
        ]);

        return redirect()
            ->route('timeclock.index', ['location_id' => $location->id])
            ->with('status', 'Clock out recorded.');
    }

    /**
     * @return array{0: Tenant, 1: Location, 2?: Collection<int, Location>}
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
