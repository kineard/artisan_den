# MVP Closeout Tracker

Date started: 2026-02-28
Owner: Artisan Den migration effort

## Progress Legend

- Status: `NOT_STARTED` | `IN_PROGRESS` | `DONE` | `BLOCKED`
- Reporting format in updates:
  - `Step X of 5`
  - `Slice Y of 10`

## Step Plan (5 total)

1. Source data completion (legacy inventory/timeclock source tables ready)
2. Full import execution (all modules)
3. Parity validation (counts + targeted record checks)
4. UAT/business validation (real workflows)
5. Cutover rehearsal + go-live checklist

## Slice Plan (10 total)

1. Confirm source table readiness
2. Capture baseline readiness report
3. Import: core (stores + KPI)
4. Import: inventory
5. Import: timeclock
6. Verify parity counts (all modules)
7. Verify critical money/value records
8. UAT workflow pass
9. Cutover rehearsal dry run
10. Final go-live signoff

## Current Status

- Step 1 of 5: `IN_PROGRESS`
- Slice 1 of 10: `IN_PROGRESS`
- Overall completion: `0/5 steps done`, `0/10 slices done`

## Step 1 Completion Criteria

- Legacy source DB includes required inventory source tables:
  - `products`, `inventory`, `orders`
- Legacy source DB includes required timeclock source tables:
  - `employees`, `time_shifts`, `time_punch_events`
- Preflight report no longer warns about missing source tables for those modules.

## Execution Log

### 2026-02-28

- Initialized tracker and started Step 1.
- Baseline captured:
  - `php artisan preflight:history --module=all`
  - `php artisan preflight:brief`
  - Result: `PASS=13 WARN=2 FAIL=0`
  - Active warnings:
    - `legacy_inventory_tables` missing in source
    - `legacy_timeclock_tables` missing in source
- Artifacts updated:
  - `docs/reports/preflight/preflight-all-20260228_161410.json`
  - `docs/reports/preflight/preflight-latest.json`
  - `docs/reports/preflight/preflight-latest.md`
- Step 1 remains in progress until source tables are available.

## Next Immediate Actions (Step 1 of 5)

1. Obtain source assets (legacy DB or SQL dump) containing missing inventory/timeclock tables.
2. Re-run:
   - `php artisan preflight:check --module=inventory --json`
   - `php artisan preflight:check --module=timeclock --json`
3. Mark Slice 1 done once both warnings clear.

## Blockers / Needed Assets

- If Step 1 remains blocked, provide one of:
  - legacy DB with inventory/timeclock tables populated, or
  - SQL dump containing those tables/data, or
  - migration scripts + seed data source for those tables.
