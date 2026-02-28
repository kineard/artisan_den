# Lightspeed X Sync Progress

## Completed in this slice
- Added env loader helper for `.env.local` / `.env` in `includes/helpers.php` via `getAppEnv()`.
- Added Lightspeed X API client in `includes/integrations/lightspeedx-client.php`.
- Added sync service/orchestration in `includes/integrations/lightspeedx-sync.php`:
  - sync run start/finish logging
  - error logging
  - category/product normalization + upsert
- Added CLI entrypoint `scripts/lightspeed-sync.php`.
- Normalized migration `database/migrate-lightspeed-sync.sql`:
  - sync run/error/checkpoint tables
  - lightspeed category/product mapping tables
  - app-facing category map tables
- Added `.env` and `.env.local` to `.gitignore`.

## Next runbook (manual)
1. Populate `.env.local`:
   - `LIGHTSPEED_X_STORE_HOST=drvapeit.retail.lightspeed.app`
   - `LIGHTSPEED_X_ACCESS_TOKEN=<private-app-token>`
2. Run migration:
   - `PGPASSWORD=artisan_pass_123 psql -h localhost -U artisan_user -d artisan_den -f database/migrate-lightspeed-sync.sql`
3. Run categories sync:
   - `php scripts/lightspeed-sync.php --entity=categories --limit=200 --max-pages=20`
4. Run products sync:
   - `php scripts/lightspeed-sync.php --entity=products --limit=200 --max-pages=20`
5. Run full sync:
   - `php scripts/lightspeed-sync.php --entity=all --limit=200 --max-pages=20`

## Crash recovery checkpoints
- If sync fails, inspect latest rows in:
  - `integration_sync_runs`
  - `integration_sync_errors`
- Re-run safely: upserts are idempotent by external IDs/SKU.
