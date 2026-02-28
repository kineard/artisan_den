# Legacy Import Runbook (Laravel)

This runbook covers importing data from legacy DB `artisan_den` into Laravel DB `artisan_den_laravel`.

## Command

From `laravel-app/`:

```bash
php artisan legacy:import --dry-run
php artisan legacy:import --module=core
php artisan legacy:import --module=core --cleanup-main-location
php artisan legacy:verify --module=core
php artisan preflight:check --module=all
php artisan preflight:check --module=all --json
php artisan preflight:history --module=all
php artisan preflight:brief
```

## Available Options

- `--dry-run`  
  Preview import counts, no writes.

- `--module=all|core|kpi|inventory|timeclock`  
  Controls import scope.

- `--cleanup-main-location`  
  Deactivates scaffold `Main Location` if imported stores exist and there are no linked records on `Main Location`.

## Preflight (single readiness report)

Run this before cutover checks or after import runs:

```bash
php artisan preflight:check --module=all
```

For automation/checklists, use machine-readable output:

```bash
php artisan preflight:check --module=all --json
```

For auditable history snapshots (timestamped JSON files):

```bash
php artisan preflight:history --module=all
```

Default report directory:
- `docs/reports/preflight/`
- Writes both:
  - `preflight-<module>-YYYYMMDD_HHMMSS.json`
  - `preflight-latest.json` (updated each run)

For senior-review readability, generate a Markdown brief from latest JSON:

```bash
php artisan preflight:brief
```

Default brief file:
- `docs/reports/preflight/preflight-latest.md`

What it validates:
- Laravel DB and legacy DB connectivity.
- Route hardening (auth + permission middleware on module routes).
- Core parity signals (`stores` vs active `locations`, `daily_kpis` vs `kpi_dailies`).
- Inventory/timeclock source-table availability (warns when source tables are not present yet).
- Module scoping:
  - `--module=inventory` checks inventory route protections + inventory source-table availability.
  - `--module=timeclock` checks timeclock route protections + timeclock source-table availability.

## Current Reality (today)

Legacy DB currently contains:
- `stores`
- `daily_kpis` (currently 0 rows)

Legacy DB currently does **not** contain inventory/timeclock transactional tables, so those modules are skipped with warnings.

## Mapping Implemented

- `stores` -> `locations` (within first tenant)
- `daily_kpis` -> `kpi_dailies` (`*_cents` conversion)
- Inventory/timeclock import paths are now implemented and auto-activate when legacy tables exist:
  - Inventory: `vendors`, `products`, `inventory`, `orders`
  - Timeclock: `employees`, `employee_locations`, `time_shifts`, `time_punch_events`
  - If source tables are missing, import logs a warning and safely skips.

## Environment Config

Configured in `laravel-app/.env`:

```bash
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=5432
LEGACY_DB_DATABASE=artisan_den
LEGACY_DB_USERNAME=artisan_user
LEGACY_DB_PASSWORD=artisan_pass_123
```

and in `config/database.php` as connection: `legacy_pgsql`.

## Safe Execution Sequence

1. Run dry run:
   ```bash
   php artisan legacy:import --dry-run
   ```
2. Run scoped import:
   ```bash
   php artisan legacy:import --module=core
   ```
3. Verify in UI:
   - login
   - check location dropdowns in KPI/Inventory/Timeclock
   - check KPI history list
4. Verify parity report:
   ```bash
   php artisan legacy:verify --module=core
   ```

