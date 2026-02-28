<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

class VerifyLegacyImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:verify {--module=all : all|core|kpi|inventory|timeclock}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify legacy-to-laravel import parity with count comparisons';

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

        $legacy = DB::connection('legacy_pgsql');
        $target = DB::connection();

        $this->info('Legacy import verification report');

        $legacyStores = $this->safeCount($legacy, 'stores');
        $targetLocations = $this->safeCount($target, 'locations');
        $targetActiveLocations = $target->getSchemaBuilder()->hasTable('locations')
            ? (int)$target->table('locations')->where('is_active', true)->count()
            : 0;
        $this->line("stores -> locations: legacy={$legacyStores}, target_active={$targetActiveLocations}, target_total={$targetLocations}");

        if ($module === 'all' || $module === 'core' || $module === 'kpi') {
            $legacyKpis = $this->safeCount($legacy, 'daily_kpis');
            $targetKpis = $this->safeCount($target, 'kpi_dailies');
            $this->line("daily_kpis -> kpi_dailies: legacy={$legacyKpis}, target={$targetKpis}");
        }

        if ($module === 'all' || $module === 'inventory') {
            $legacyProducts = $this->safeCount($legacy, 'products');
            $legacyInventory = $this->safeCount($legacy, 'inventory');
            $legacyOrders = $this->safeCount($legacy, 'orders');
            $this->line("inventory source tables: products={$legacyProducts}, inventory={$legacyInventory}, orders={$legacyOrders}");
            $this->line("inventory target tables: products={$this->safeCount($target, 'products')}, inventory_items={$this->safeCount($target, 'inventory_items')}, purchase_orders={$this->safeCount($target, 'purchase_orders')}");
        }

        if ($module === 'all' || $module === 'timeclock') {
            $legacyEmployees = $this->safeCount($legacy, 'employees');
            $legacyShifts = $this->safeCount($legacy, 'time_shifts');
            $legacyPunches = $this->safeCount($legacy, 'time_punch_events');
            $this->line("timeclock source tables: employees={$legacyEmployees}, shifts={$legacyShifts}, punches={$legacyPunches}");
            $this->line("timeclock target tables: employees={$this->safeCount($target, 'timeclock_employees')}, shifts={$this->safeCount($target, 'time_shifts')}, punches={$this->safeCount($target, 'time_punch_events')}");
        }

        $this->info('Verification complete.');
        return self::SUCCESS;
    }

    private function safeCount($connection, string $table): int
    {
        if (!$connection->getSchemaBuilder()->hasTable($table)) {
            return 0;
        }
        return (int)$connection->table($table)->count();
    }
}
