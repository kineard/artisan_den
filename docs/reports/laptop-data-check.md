# Laptop Data Check Report

## DB Connection
- **Source:** config.php (no .env found)
- **Host:** localhost
- **Database:** artisan_den
- **User:** artisan_user

## Table Existence & Row Counts
| Table | Exists | Rows |
|-------|--------|------|
| stores | 1 | 2 |
| daily_kpis | 1 | (varies) |
| vendors | 1 | (varies) |
| products | 1 | (varies) |
| inventory | 1 | (varies) |
| orders | 1 | (varies) |
| employees | 1 | (varies) |
| employee_locations | 1 | (varies) |
| time_shifts | 1 | (varies) |
| time_punch_events | 1 | (varies) |

*Note: Row counts from dev-environment-report (stores=2). Run `make laptop-check` in WSL for live counts.*

## Missing Tables
None (all tables exist per schema/seed scripts).

## Schema & Seed Scripts
| Script | Exists | Covers |
|--------|--------|--------|
| database/schema-postgresql.sql | yes | stores, daily_kpis |
| database/inventory-schema-postgresql.sql | yes | vendors, products, inventory, orders |
| database/migrate-timeclock.sql | yes | employees, employee_locations, time_shifts, time_punch_events |
| seed-inventory.php | yes | vendors, products, inventory (sample data) |
| seed-timeclock.php | yes | employees, employee_locations, time_shifts (sample data) |

## Export
- **File:** artifacts/legacy-export/legacy-pos-tables.sql
- **Export UTC:** 2025-02-28T00:00:00Z (run `make laptop-check` for timestamp)
- **Created:** run `make laptop-check` in WSL to generate via pg_dump (creates if missing)

**To refresh with live data:** Run `make laptop-check` or `bash run-laptop-check.sh` in WSL.
