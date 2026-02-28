<?php

namespace App\Console\Commands;

use App\Models\KpiDaily;
use App\Models\Location;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\TimePunchEvent;
use App\Models\TimeShift;
use App\Models\TimeclockEmployee;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Console\Command;

class ImportLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:import
                            {--module=all : all|core|kpi|inventory|timeclock}
                            {--dry-run : Preview import counts without writing}
                            {--cleanup-main-location : Deactivate scaffold Main Location when imported stores exist and it has no linked records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import legacy artisan_den PostgreSQL data into Laravel schema';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $module = strtolower((string)$this->option('module'));
        $dryRun = (bool)$this->option('dry-run');
        if (!in_array($module, ['all', 'core', 'kpi', 'inventory', 'timeclock'], true)) {
            $this->error('Invalid module. Use: all|core|kpi|inventory|timeclock');
            return self::FAILURE;
        }

        $this->info('Starting legacy import' . ($dryRun ? ' (dry run)' : '') . '...');

        try {
            DB::connection('legacy_pgsql')->select('SELECT 1');
            DB::connection()->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->error('Database connection check failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $tenant = Tenant::query()->first();
        if ($tenant === null && !$dryRun) {
            $tenant = Tenant::query()->create([
                'name' => 'Imported Legacy Business',
                'slug' => 'imported-legacy-business',
                'status' => 'active',
            ]);
        }
        if ($tenant === null) {
            // Dry run path: use placeholder tenant id for reporting only.
            $tenant = new Tenant(['id' => 1, 'name' => 'Dry Run Tenant']);
            $tenant->id = 1;
        }

        $locationMap = $this->importStores((int)$tenant->id, $dryRun);

        if ((bool)$this->option('cleanup-main-location')) {
            $this->cleanupMainLocation((int)$tenant->id, $dryRun);
        }

        if ($module === 'all' || $module === 'core' || $module === 'kpi') {
            $this->importKpis((int)$tenant->id, $locationMap, $dryRun);
        }

        if ($module === 'all' || $module === 'inventory') {
            $this->importInventory((int)$tenant->id, $locationMap, $dryRun);
        }

        if ($module === 'all' || $module === 'timeclock') {
            $this->importTimeclock((int)$tenant->id, $locationMap, $dryRun);
        }

        $this->info('Legacy import complete' . ($dryRun ? ' (dry run)' : '') . '.');
        return self::SUCCESS;
    }

    /**
     * @return array<int, int> legacy store_id => laravel location_id
     */
    private function importStores(int $tenantId, bool $dryRun): array
    {
        $legacy = DB::connection('legacy_pgsql');
        if (!$legacy->getSchemaBuilder()->hasTable('stores')) {
            $this->warn('Legacy table missing: stores');
            return [];
        }

        $rows = $legacy->table('stores')->orderBy('id')->get();
        $this->line('Stores found in legacy DB: ' . $rows->count());
        $map = [];
        $imported = 0;

        foreach ($rows as $row) {
            $name = trim((string)($row->name ?? ''));
            if ($name === '') {
                continue;
            }

            if ($dryRun) {
                $imported++;
                continue;
            }

            $location = Location::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => $name,
                ],
                [
                    'code' => Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]+/', '', $name), 0, 8)) ?: null,
                    'is_active' => true,
                ]
            );

            $map[(int)$row->id] = (int)$location->id;
            $imported++;
        }

        $this->info('Stores imported: ' . $imported);

        if ($dryRun) {
            // Build a fallback map in dry-run mode for KPI counting by store id.
            foreach ($rows as $row) {
                $map[(int)$row->id] = (int)$row->id;
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $storeToLocation
     */
    private function importKpis(int $tenantId, array $storeToLocation, bool $dryRun): void
    {
        $legacy = DB::connection('legacy_pgsql');
        if (!$legacy->getSchemaBuilder()->hasTable('daily_kpis')) {
            $this->warn('Legacy table missing: daily_kpis');
            return;
        }

        $rows = $legacy->table('daily_kpis')->orderBy('entry_date')->get();
        $this->line('KPI rows found in legacy DB: ' . $rows->count());

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $storeId = (int)($row->store_id ?? 0);
            $locationId = $storeToLocation[$storeId] ?? 0;
            if ($locationId <= 0) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $imported++;
                continue;
            }

            KpiDaily::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'entry_date' => (string)$row->entry_date,
                ],
                [
                    'bank_balance_cents' => $this->decimalToCents($row->bank_balance ?? 0),
                    'safe_balance_cents' => $this->decimalToCents($row->safe_balance ?? 0),
                    'sales_today_cents' => $this->decimalToCents($row->sales_today ?? 0),
                    'cogs_today_cents' => $this->decimalToCents($row->cogs_today ?? 0),
                    'labor_today_cents' => $this->decimalToCents($row->labor_today ?? 0),
                    'avg_daily_overhead_cents' => $this->decimalToCents($row->avg_daily_overhead ?? 0),
                ]
            );
            $imported++;
        }

        $this->info('KPI rows imported: ' . $imported);
        if ($skipped > 0) {
            $this->warn('KPI rows skipped (missing store/location mapping): ' . $skipped);
        }
    }

    private function cleanupMainLocation(int $tenantId, bool $dryRun): void
    {
        $main = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'Main Location')
            ->first();
        if ($main === null) {
            $this->line('Cleanup: no "Main Location" found.');
            return;
        }

        $otherActive = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $main->id)
            ->where('is_active', true)
            ->count();
        if ($otherActive <= 0) {
            $this->line('Cleanup: skipped (no other active locations).');
            return;
        }

        $hasRefs =
            KpiDaily::query()->where('location_id', $main->id)->exists() ||
            DB::table('inventory_items')->where('location_id', $main->id)->exists() ||
            DB::table('purchase_orders')->where('location_id', $main->id)->exists() ||
            DB::table('timeclock_employees')->where('location_id', $main->id)->exists() ||
            DB::table('time_shifts')->where('location_id', $main->id)->exists() ||
            DB::table('time_punch_events')->where('location_id', $main->id)->exists() ||
            DB::table('users')->where('location_id', $main->id)->exists();

        if ($hasRefs) {
            $this->warn('Cleanup: skipped (Main Location has linked records).');
            return;
        }

        if ($dryRun) {
            $this->info('Cleanup: would deactivate Main Location (id=' . $main->id . ').');
            return;
        }

        $main->update(['is_active' => false]);
        $this->info('Cleanup: deactivated Main Location (id=' . $main->id . ').');
    }

    /**
     * @param array<int, int> $storeToLocation
     */
    private function importInventory(int $tenantId, array $storeToLocation, bool $dryRun): void
    {
        $legacy = DB::connection('legacy_pgsql');
        if (
            !$legacy->getSchemaBuilder()->hasTable('products') ||
            !$legacy->getSchemaBuilder()->hasTable('inventory') ||
            !$legacy->getSchemaBuilder()->hasTable('orders')
        ) {
            $this->warn('Inventory import bridge: legacy inventory tables not present yet (products/inventory/orders).');
            return;
        }

        $vendorMap = $this->importLegacyVendors($tenantId, $dryRun);
        $productMap = $this->importLegacyProducts($tenantId, $dryRun);
        $this->importLegacyInventoryItems($tenantId, $storeToLocation, $productMap, $dryRun);
        $this->importLegacyOrders($tenantId, $storeToLocation, $productMap, $vendorMap, $dryRun);
    }

    /**
     * @param array<int, int> $storeToLocation
     */
    private function importTimeclock(int $tenantId, array $storeToLocation, bool $dryRun): void
    {
        $legacy = DB::connection('legacy_pgsql');
        if (
            !$legacy->getSchemaBuilder()->hasTable('employees') ||
            !$legacy->getSchemaBuilder()->hasTable('time_shifts') ||
            !$legacy->getSchemaBuilder()->hasTable('time_punch_events')
        ) {
            $this->warn('Time clock import bridge: legacy timeclock tables not present yet (employees/time_shifts/time_punch_events).');
            return;
        }

        $employeeMap = $this->importLegacyEmployees($tenantId, $storeToLocation, $dryRun);
        $shiftMap = $this->importLegacyShifts($tenantId, $storeToLocation, $employeeMap, $dryRun);
        $this->importLegacyPunchEvents($tenantId, $storeToLocation, $employeeMap, $shiftMap, $dryRun);
    }

    /**
     * @return array<int, int> legacy vendor_id => laravel vendor_id
     */
    private function importLegacyVendors(int $tenantId, bool $dryRun): array
    {
        $legacy = DB::connection('legacy_pgsql');
        if (!$legacy->getSchemaBuilder()->hasTable('vendors')) {
            $this->line('Vendors import: skipped (legacy vendors table missing).');
            return [];
        }

        $rows = $legacy->table('vendors')->orderBy('id')->get();
        $map = [];
        $imported = 0;
        foreach ($rows as $row) {
            $name = trim((string)($row->name ?? ''));
            if ($name === '') {
                continue;
            }
            if ($dryRun) {
                $imported++;
                continue;
            }
            $vendor = Vendor::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                [
                    'contact_name' => trim((string)($row->contact_name ?? '')) ?: null,
                    'contact_phone' => trim((string)($row->phone ?? '')) ?: null,
                    'is_active' => (bool)($row->is_active ?? true),
                ]
            );
            $map[(int)$row->id] = (int)$vendor->id;
            $imported++;
        }
        $this->info('Vendors imported: ' . $imported);
        return $map;
    }

    /**
     * @return array<int, int> legacy product_id => laravel product_id
     */
    private function importLegacyProducts(int $tenantId, bool $dryRun): array
    {
        $legacy = DB::connection('legacy_pgsql');
        $rows = $legacy->table('products')->orderBy('id')->get();
        $map = [];
        $imported = 0;
        foreach ($rows as $row) {
            $sku = trim((string)($row->sku ?? ''));
            $name = trim((string)($row->name ?? ''));
            if ($sku === '' || $name === '') {
                continue;
            }
            if ($dryRun) {
                $imported++;
                continue;
            }
            $product = Product::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'sku' => $sku],
                [
                    'name' => $name,
                    'unit' => trim((string)($row->unit_type ?? 'unit')) ?: 'unit',
                    'is_active' => true,
                ]
            );
            $map[(int)$row->id] = (int)$product->id;
            $imported++;
        }
        $this->info('Products imported: ' . $imported);
        return $map;
    }

    /**
     * @param array<int, int> $storeToLocation
     * @param array<int, int> $productMap
     */
    private function importLegacyInventoryItems(int $tenantId, array $storeToLocation, array $productMap, bool $dryRun): void
    {
        $legacy = DB::connection('legacy_pgsql');
        $rows = $legacy->table('inventory')->orderBy('id')->get();
        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $locationId = $storeToLocation[(int)($row->store_id ?? 0)] ?? 0;
            $productId = $productMap[(int)($row->product_id ?? 0)] ?? 0;
            if ($locationId <= 0 || $productId <= 0) {
                $skipped++;
                continue;
            }
            if ($dryRun) {
                $imported++;
                continue;
            }

            $onHand = (float)($row->on_hand ?? 0);
            $reorder = (float)($row->reorder_point ?? 0);
            $status = $onHand <= 0 ? 'out' : ($onHand <= $reorder ? 'low' : 'ok');

            InventoryItem::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'product_id' => $productId,
                ],
                [
                    'on_hand' => $onHand,
                    'reorder_point' => $reorder,
                    'target_max' => (float)($row->target_max ?? 0),
                    'last_cost_cents' => $this->decimalToCents($row->unit_cost ?? 0),
                    'status' => $status,
                ]
            );
            $imported++;
        }
        $this->info('Inventory rows imported: ' . $imported);
        if ($skipped > 0) {
            $this->warn('Inventory rows skipped (missing store/product mapping): ' . $skipped);
        }
    }

    /**
     * @param array<int, int> $storeToLocation
     * @param array<int, int> $productMap
     * @param array<int, int> $vendorMap
     */
    private function importLegacyOrders(int $tenantId, array $storeToLocation, array $productMap, array $vendorMap, bool $dryRun): void
    {
        $legacy = DB::connection('legacy_pgsql');
        $rows = $legacy->table('orders')->orderBy('id')->get();
        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $locationId = $storeToLocation[(int)($row->store_id ?? 0)] ?? 0;
            $productId = $productMap[(int)($row->product_id ?? 0)] ?? 0;
            if ($locationId <= 0 || $productId <= 0) {
                $skipped++;
                continue;
            }

            $legacyStatus = strtoupper((string)($row->status ?? 'REQUESTED'));
            $status = in_array($legacyStatus, ['RECEIVED', 'STOCKED'], true) ? 'received' : 'pending';
            $qty = (float)($row->quantity ?? 0);
            $qtyReceived = $status === 'received' ? $qty : 0.0;

            if ($dryRun) {
                $imported++;
                continue;
            }

            PurchaseOrder::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'product_id' => $productId,
                    'order_date' => (string)($row->order_date ?? now()->toDateString()),
                    'status' => $status,
                    'quantity_ordered' => $qty,
                ],
                [
                    'vendor_id' => $vendorMap[(int)($row->vendor_id ?? 0)] ?? null,
                    'quantity_received' => $qtyReceived,
                    'unit_cost_cents' => $this->decimalToCents($row->unit_cost ?? 0),
                    'expected_delivery_date' => null,
                    'received_date' => $row->received_date ?? null,
                ]
            );
            $imported++;
        }
        $this->info('Orders imported: ' . $imported);
        if ($skipped > 0) {
            $this->warn('Orders skipped (missing store/product mapping): ' . $skipped);
        }
    }

    /**
     * @param array<int, int> $storeToLocation
     * @return array<int, int> legacy employee_id => laravel employee_id
     */
    private function importLegacyEmployees(int $tenantId, array $storeToLocation, bool $dryRun): array
    {
        $legacy = DB::connection('legacy_pgsql');
        $employeeLocations = [];
        if ($legacy->getSchemaBuilder()->hasTable('employee_locations')) {
            $employeeLocationRows = $legacy->table('employee_locations')
                ->where('is_active', true)
                ->orderBy('id')
                ->get();
            foreach ($employeeLocationRows as $row) {
                $empId = (int)($row->employee_id ?? 0);
                if ($empId > 0 && !array_key_exists($empId, $employeeLocations)) {
                    $employeeLocations[$empId] = (int)($row->store_id ?? 0);
                }
            }
        }

        $defaultLocationId = (int)(Location::query()->where('tenant_id', $tenantId)->where('is_active', true)->value('id') ?? 0);
        $rows = $legacy->table('employees')->orderBy('id')->get();
        $map = [];
        $imported = 0;
        foreach ($rows as $row) {
            $legacyEmpId = (int)($row->id ?? 0);
            if ($legacyEmpId <= 0) {
                continue;
            }
            $storeId = $employeeLocations[$legacyEmpId] ?? 0;
            $locationId = $storeToLocation[$storeId] ?? $defaultLocationId;
            if ($locationId <= 0) {
                continue;
            }
            if ($dryRun) {
                $imported++;
                continue;
            }
            $employee = TimeclockEmployee::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'full_name' => trim((string)($row->full_name ?? 'Employee ' . $legacyEmpId)),
                ],
                [
                    'role_name' => trim((string)($row->role_name ?? 'Employee')) ?: 'Employee',
                    'email' => trim((string)($row->email ?? '')) ?: null,
                    'pin_hash' => trim((string)($row->pin_hash ?? '')) ?: bcrypt('1234'),
                    'hourly_rate_cents' => (int)($row->hourly_rate_cents ?? 0),
                    'is_active' => (bool)($row->is_active ?? true),
                ]
            );
            $map[$legacyEmpId] = (int)$employee->id;
            $imported++;
        }
        $this->info('Timeclock employees imported: ' . $imported);
        return $map;
    }

    /**
     * @param array<int, int> $storeToLocation
     * @param array<int, int> $employeeMap
     * @return array<int, int> legacy shift_id => laravel shift_id
     */
    private function importLegacyShifts(int $tenantId, array $storeToLocation, array $employeeMap, bool $dryRun): array
    {
        $legacy = DB::connection('legacy_pgsql');
        $rows = $legacy->table('time_shifts')->orderBy('id')->get();
        $map = [];
        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $locationId = $storeToLocation[(int)($row->store_id ?? 0)] ?? 0;
            $employeeId = $employeeMap[(int)($row->employee_id ?? 0)] ?? 0;
            if ($locationId <= 0 || $employeeId <= 0) {
                $skipped++;
                continue;
            }
            if ($dryRun) {
                $imported++;
                continue;
            }
            $shift = TimeShift::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'timeclock_employee_id' => $employeeId,
                    'clock_in_at' => (string)($row->clock_in_utc ?? now()),
                ],
                [
                    'clock_out_at' => $row->clock_out_utc ?? null,
                    'clock_in_source' => 'legacy',
                    'clock_out_source' => $row->clock_out_utc ? 'legacy' : null,
                    'clock_in_note' => trim((string)($row->clock_in_note ?? '')) ?: null,
                    'clock_out_note' => trim((string)($row->clock_out_note ?? '')) ?: null,
                ]
            );
            $map[(int)$row->id] = (int)$shift->id;
            $imported++;
        }
        $this->info('Time shifts imported: ' . $imported);
        if ($skipped > 0) {
            $this->warn('Time shifts skipped (missing store/employee mapping): ' . $skipped);
        }
        return $map;
    }

    /**
     * @param array<int, int> $storeToLocation
     * @param array<int, int> $employeeMap
     * @param array<int, int> $shiftMap
     */
    private function importLegacyPunchEvents(int $tenantId, array $storeToLocation, array $employeeMap, array $shiftMap, bool $dryRun): void
    {
        $legacy = DB::connection('legacy_pgsql');
        $rows = $legacy->table('time_punch_events')->orderBy('id')->get();
        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $locationId = $storeToLocation[(int)($row->store_id ?? 0)] ?? 0;
            $employeeId = $employeeMap[(int)($row->employee_id ?? 0)] ?? 0;
            if ($locationId <= 0 || $employeeId <= 0) {
                $skipped++;
                continue;
            }
            if ($dryRun) {
                $imported++;
                continue;
            }

            TimePunchEvent::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'timeclock_employee_id' => $employeeId,
                    'event_type' => (string)($row->event_type ?? 'CLOCK_IN'),
                    'event_at' => (string)($row->event_utc ?? now()),
                ],
                [
                    'time_shift_id' => $shiftMap[(int)($row->shift_id ?? 0)] ?? null,
                    'source' => trim((string)($row->created_by ?? 'legacy')) ?: 'legacy',
                    'note' => trim((string)($row->note ?? '')) ?: null,
                ]
            );
            $imported++;
        }
        $this->info('Time punch events imported: ' . $imported);
        if ($skipped > 0) {
            $this->warn('Time punch events skipped (missing store/employee mapping): ' . $skipped);
        }
    }

    /**
     * @param mixed $value
     */
    private function decimalToCents($value): int
    {
        $number = is_numeric($value) ? (float)$value : 0.0;
        return (int) round($number * 100);
    }
}
