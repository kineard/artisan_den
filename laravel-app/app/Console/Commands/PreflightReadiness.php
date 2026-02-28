<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Console\Command;

class PreflightReadiness extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preflight:check
                            {--module=all : all|core|kpi|inventory|timeclock}
                            {--json : Output machine-readable JSON report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run consolidated readiness checks for Laravel migration/cutover';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $module = strtolower((string)$this->option('module'));
        if (!in_array($module, ['all', 'core', 'kpi', 'inventory', 'timeclock'], true)) {
            $this->error('Invalid module. Use: all|core|kpi|inventory|timeclock');
            return self::FAILURE;
        }

        $jsonOutput = (bool)$this->option('json');
        $results = [];
        $warnCount = 0;
        $failCount = 0;

        $add = function (string $status, string $check, string $detail) use (&$results, &$warnCount, &$failCount): void {
            $results[] = [
                'status' => $status,
                'check' => $check,
                'detail' => $detail,
            ];
            if ($status === 'WARN') {
                $warnCount++;
            } elseif ($status === 'FAIL') {
                $failCount++;
            }
        };

        // Connectivity checks
        try {
            DB::connection()->select('SELECT 1');
            $add('PASS', 'laravel_db_connection', 'Connected to Laravel DB.');
        } catch (\Throwable $e) {
            $add('FAIL', 'laravel_db_connection', $e->getMessage());
        }

        try {
            DB::connection('legacy_pgsql')->select('SELECT 1');
            $add('PASS', 'legacy_db_connection', 'Connected to legacy DB.');
        } catch (\Throwable $e) {
            $add('FAIL', 'legacy_db_connection', $e->getMessage());
        }

        // Route hardening checks
        $routeNames = collect(Route::getRoutes())->map(fn ($r) => [
            'uri' => (string)$r->uri(),
            'methods' => (array)$r->methods(),
            'name' => (string)($r->getName() ?? ''),
            'middleware' => (array)$r->gatherMiddleware(),
        ]);

        $requiredAuthRoutes = [];
        if ($module === 'all' || $module === 'core' || $module === 'kpi') {
            $requiredAuthRoutes[] = 'kpi.index';
        }
        if ($module === 'all' || $module === 'inventory') {
            $requiredAuthRoutes[] = 'inventory.index';
        }
        if ($module === 'all' || $module === 'timeclock') {
            $requiredAuthRoutes[] = 'timeclock.index';
        }
        foreach ($requiredAuthRoutes as $name) {
            $route = $routeNames->first(fn ($r) => $r['name'] === $name);
            if (!$route) {
                $add('FAIL', "route_exists:$name", 'Route missing.');
                continue;
            }
            $hasAuth = in_array('auth.session', $route['middleware'], true);
            $add($hasAuth ? 'PASS' : 'FAIL', "route_auth:$name", $hasAuth ? 'auth.session applied.' : 'auth.session missing.');
        }

        $requiredPermissionRoutes = [];
        if ($module === 'all' || $module === 'core' || $module === 'kpi') {
            $requiredPermissionRoutes['kpi.store'] = 'permission:kpi_write';
        }
        if ($module === 'all' || $module === 'inventory') {
            $requiredPermissionRoutes['inventory.products.store'] = 'permission:inventory_write';
            $requiredPermissionRoutes['inventory.items.update'] = 'permission:inventory_write';
            $requiredPermissionRoutes['inventory.items.order'] = 'permission:inventory_write';
        }
        if ($module === 'all' || $module === 'timeclock') {
            $requiredPermissionRoutes['timeclock.employees.store'] = 'permission:timeclock_manager';
            $requiredPermissionRoutes['timeclock.punch'] = 'permission:employee_self_service';
        }
        foreach ($requiredPermissionRoutes as $name => $perm) {
            $route = $routeNames->first(fn ($r) => $r['name'] === $name);
            if (!$route) {
                $add('FAIL', "route_exists:$name", 'Route missing.');
                continue;
            }
            $hasPerm = in_array($perm, $route['middleware'], true);
            $add($hasPerm ? 'PASS' : 'FAIL', "route_permission:$name", $hasPerm ? "$perm applied." : "$perm missing.");
        }

        // Core parity checks
        if ($module === 'all' || $module === 'core' || $module === 'kpi') {
            $legacyStores = $this->safeCount('legacy_pgsql', 'stores');
            $targetActiveLocations = $this->safeCount('pgsql', 'locations', ['is_active' => true]);
            if ($targetActiveLocations >= $legacyStores && $legacyStores > 0) {
                $add('PASS', 'stores_to_locations_parity', "legacy={$legacyStores}, target_active={$targetActiveLocations}");
            } elseif ($legacyStores === 0) {
                $add('WARN', 'stores_to_locations_parity', 'No legacy stores found.');
            } else {
                $add('WARN', 'stores_to_locations_parity', "legacy={$legacyStores}, target_active={$targetActiveLocations}");
            }

            $legacyKpi = $this->safeCount('legacy_pgsql', 'daily_kpis');
            $targetKpi = $this->safeCount('pgsql', 'kpi_dailies');
            if ($targetKpi >= $legacyKpi) {
                $add('PASS', 'kpi_rows_parity', "legacy={$legacyKpi}, target={$targetKpi}");
            } else {
                $add('WARN', 'kpi_rows_parity', "legacy={$legacyKpi}, target={$targetKpi}");
            }
        }

        // Module-availability warnings (not failures)
        if ($module === 'all' || $module === 'inventory') {
            $hasLegacyInventory = $this->hasLegacyTable('products') && $this->hasLegacyTable('inventory') && $this->hasLegacyTable('orders');
            $add($hasLegacyInventory ? 'PASS' : 'WARN', 'legacy_inventory_tables', $hasLegacyInventory ? 'products/inventory/orders available.' : 'Legacy inventory tables not available yet.');
        }
        if ($module === 'all' || $module === 'timeclock') {
            $hasLegacyTimeclock = $this->hasLegacyTable('employees') && $this->hasLegacyTable('time_shifts') && $this->hasLegacyTable('time_punch_events');
            $add($hasLegacyTimeclock ? 'PASS' : 'WARN', 'legacy_timeclock_tables', $hasLegacyTimeclock ? 'employees/time_shifts/time_punch_events available.' : 'Legacy timeclock tables not available yet.');
        }

        $passCount = collect($results)->where('status', 'PASS')->count();
        $overallStatus = $failCount > 0 ? 'FAIL' : ($warnCount > 0 ? 'WARN' : 'PASS');

        if ($jsonOutput) {
            $payload = [
                'command' => 'preflight:check',
                'module' => $module,
                'overall_status' => $overallStatus,
                'summary' => [
                    'pass' => $passCount,
                    'warn' => $warnCount,
                    'fail' => $failCount,
                ],
                'checks' => $results,
            ];
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Status', 'Check', 'Detail'],
                collect($results)->map(fn (array $row) => [$row['status'], $row['check'], $row['detail']])->all()
            );
            $this->line("Summary: PASS={$passCount} WARN={$warnCount} FAIL={$failCount}");
        }

        if ($failCount > 0) {
            if (!$jsonOutput) {
                $this->error('Preflight failed.');
            }
            return self::FAILURE;
        }
        if ($warnCount > 0) {
            if (!$jsonOutput) {
                $this->warn('Preflight completed with warnings.');
            }
            return self::SUCCESS;
        }

        if (!$jsonOutput) {
            $this->info('Preflight passed with no warnings.');
        }
        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $where
     */
    private function safeCount(string $connection, string $table, array $where = []): int
    {
        $db = DB::connection($connection === 'pgsql' ? null : $connection);
        if (!$db->getSchemaBuilder()->hasTable($table)) {
            return 0;
        }
        $query = $db->table($table);
        foreach ($where as $key => $value) {
            $query->where($key, $value);
        }
        return (int)$query->count();
    }

    private function hasLegacyTable(string $table): bool
    {
        return DB::connection('legacy_pgsql')->getSchemaBuilder()->hasTable($table);
    }
}
