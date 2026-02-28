# Laravel Scaffold Status

Date: 2026-02-27

## Completed

- Created Laravel app in `laravel-app/`
- Installed dependencies successfully
- Generated application key
- Configured Laravel `.env` to use local PostgreSQL credentials:
  - `DB_CONNECTION=pgsql`
  - `DB_HOST=127.0.0.1`
  - `DB_PORT=5432`
  - `DB_DATABASE=artisan_den`
  - `DB_USERNAME=artisan_user`
  - `DB_PASSWORD=artisan_pass_123`
- Set safe local drivers to avoid migration conflicts while bootstrapping:
  - `SESSION_DRIVER=file`
  - `CACHE_STORE=file`
  - `QUEUE_CONNECTION=sync`
- Verified Laravel app boots (`php artisan about`)
- Started Laravel dev server and verified HTTP 200 at:
  - `http://127.0.0.1:8002`
- Scaffolded initial Launch 1 domain models and migrations (not yet applied):
  - `Tenant`, `Location`
  - `KpiDaily`
  - `Product`, `InventoryItem`
  - `TimeclockEmployee`
- Added baseline tenant/location/role columns to Laravel `users` migration.
- Added starter model fillable/casts/relations for the above models.
- Added helper script for DB provisioning:
  - `scripts/provision-laravel-db.sh`
- Switched Laravel app to dedicated DB:
  - `DB_DATABASE=artisan_den_laravel`
- Ran all Laravel migrations successfully in dedicated DB.
- Implemented first working Laravel KPI slice:
  - Routes: `GET /kpi`, `POST /kpi`
  - Controller: `app/Http/Controllers/KpiController.php`
  - View: `resources/views/kpi/index.blade.php`
  - Behavior: location/date select, KPI save form, recent 14-day list, cents-safe storage.
- Implemented first working Laravel Inventory/Reorder slice:
  - Routes:
    - `GET /inventory`
    - `POST /inventory/products`
    - `POST /inventory/items/{item}/update`
    - `POST /inventory/items/{item}/order`
  - Controller:
    - `app/Http/Controllers/InventoryController.php`
  - View:
    - `resources/views/inventory/index.blade.php`
  - Added tables + models:
    - `vendors`, `purchase_orders`
    - `Vendor`, `PurchaseOrder`
  - Behavior:
    - Add product to inventory by location
    - Update on-hand / ROP / target / cost
    - Compute status (`ok`/`low`/`out`)
    - Place one pending order per product/location
    - Show pending qty and suggested order qty
- Added passing feature tests:
  - `tests/Feature/KpiFlowTest.php`
  - `tests/Feature/InventoryFlowTest.php`
- Implemented first working Laravel Time Clock foundation slice:
  - Routes:
    - `GET /timeclock`
    - `POST /timeclock/employees`
    - `POST /timeclock/punch`
  - Controller:
    - `app/Http/Controllers/TimeclockController.php`
  - View:
    - `resources/views/timeclock/index.blade.php`
  - Added tables + models:
    - `time_shifts`, `time_punch_events`
    - `TimeShift`, `TimePunchEvent`
  - Behavior:
    - create employee with hashed PIN
    - clock in (creates open shift + punch event)
    - clock out (closes shift + punch event)
    - reject invalid PIN and invalid state transitions
- Added passing Time Clock feature test:
  - `tests/Feature/TimeclockFlowTest.php`
- Added hardening layer (permissions, context middleware, audit logging):
  - Middleware:
    - `app/Http/Middleware/AttachAppContext.php`
    - `app/Http/Middleware/RequirePermission.php`
  - Middleware registration:
    - `bootstrap/app.php` (`AttachAppContext` appended to web middleware, `permission` alias added)
  - Route protections:
    - KPI write -> `permission:kpi_write`
    - Inventory writes -> `permission:inventory_write`
    - Time Clock employee create -> `permission:timeclock_manager`
    - Time Clock punch -> `permission:employee_self_service`
  - Centralized context resolver:
    - `app/Services/AppContextResolver.php`
  - Audit logging:
    - table/model: `audit_logs`, `AuditLog`
    - service: `app/Services/AuditLogger.php`
    - integrated into KPI/Inventory/Time Clock write actions
  - Added permission test:
    - `tests/Feature/PermissionMiddlewareTest.php` (employee denied KPI write)
- Added session-auth identity layer (no silent manager fallback):
  - Middleware:
    - `app/Http/Middleware/RequireSessionAuth.php`
  - Auth controller + login page:
    - `app/Http/Controllers/AuthController.php`
    - `resources/views/auth/login.blade.php`
  - Routes:
    - `GET /login`
    - `POST /login`
    - `POST /logout`
    - KPI/Inventory/Time Clock routes now behind `auth.session`
  - Behavior:
    - users must authenticate before accessing modules
    - permission middleware now denies unknown/missing roles instead of defaulting to manager
    - default local users auto-provisioned on login page load for development
      - `admin@artisan.local` / `admin1234`
      - `manager@artisan.local` / `manager1234`
      - `employee@artisan.local` / `employee1234`
  - Tests updated and passing with authenticated session context.
- Added shared authenticated app shell/navigation:
  - Layout: `resources/views/layouts/app.blade.php`
  - Top navigation: KPI / Inventory / Time Clock (active state)
  - Session identity display: user name + role
  - Logout action in shared header
  - Shared flash/error banners
- Migrated module views to shared layout:
  - `resources/views/kpi/index.blade.php`
  - `resources/views/inventory/index.blade.php`
  - `resources/views/timeclock/index.blade.php`
- Added legacy data import bridge (dry-run first):
  - command: `php artisan legacy:import`
  - options:
    - `--dry-run`
    - `--module=all|core|kpi|inventory|timeclock`
  - source connection: `legacy_pgsql` (configured in `config/database.php` + `.env`)
  - currently mapped:
    - `stores` -> `locations`
    - `daily_kpis` -> `kpi_dailies`
  - runbook:
    - `docs/LEGACY-IMPORT-RUNBOOK.md`
- Improved imported-location behavior:
  - context resolver now:
    - uses only active locations
    - remembers preferred location in session
    - prefers imported stores over scaffold `Main Location` when multiple exist
  - import command supports optional scaffold cleanup:
    - `--cleanup-main-location`
- Added legacy parity verification report command:
  - `php artisan legacy:verify --module=core`
- Expanded legacy import bridge for future parity:
  - inventory path implemented (vendors/products/inventory/orders)
  - timeclock path implemented (employees/employee_locations/time_shifts/time_punch_events)
  - import command auto-detects table availability and safely skips unavailable modules
  - verification command supports `--module=all` for module-level source/target counts
- Added consolidated cutover-readiness preflight command:
  - `php artisan preflight:check --module=all`
  - validates DB connectivity, route auth/permission middleware, and parity readiness signals
  - returns PASS/WARN/FAIL table in one report
  - supports `--json` for machine-readable output (for CI/checklists)
  - module-scoped checks now report only relevant route/middleware validations
- Added preflight history capture command:
  - `php artisan preflight:history --module=all`
  - saves timestamped JSON report snapshots to `docs/reports/preflight/`
  - updates `preflight-latest.json` on each run for quick reference
- Added preflight Markdown brief command:
  - `php artisan preflight:brief`
  - reads `preflight-latest.json` and writes `preflight-latest.md`
  - gives senior-review-friendly PASS/WARN/FAIL snapshot
- Stabilized test strategy to avoid wiping imported dev data:
  - feature tests now use `DatabaseTransactions` instead of `RefreshDatabase`
  - test writes are rolled back per test, preserving imported locations/data

## Why no migrations yet

To avoid conflicting with legacy tables in `artisan_den` (especially `users` and migration-owned tables), migrations were intentionally not run against the shared legacy database during scaffold setup.

## Pending (when you return)

1. Create dedicated DB for Laravel migration work (recommended):
   - `artisan_den_laravel`
2. Point Laravel `.env` to dedicated DB.
3. Run `php artisan migrate`.
4. Begin module migration in approved order:
   - KPI -> Inventory/Reorder -> Time Clock

### Quick unblock command

From project root:

```bash
./scripts/provision-laravel-db.sh
```

Then set in `laravel-app/.env`:

```bash
DB_DATABASE=artisan_den_laravel
```

Then run:

```bash
cd laravel-app
php artisan migrate
```

## Current runnable URLs

- Legacy app: `http://localhost:8001`
- Laravel scaffold: `http://127.0.0.1:8002`
- Laravel KPI page: `http://127.0.0.1:8002/kpi`
- Laravel Inventory page: `http://127.0.0.1:8002/inventory`
- Laravel Time Clock page: `http://127.0.0.1:8002/timeclock`

