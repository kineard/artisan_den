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
| daily_kpis | 1 | 106 |
| vendors | 1 | 15 |
| products | 1 | 782 |
| inventory | 1 | 1554 |
| orders | 1 | 76 |
| employees | 1 | 4 |
| employee_locations | 1 | 6 |
| time_shifts | 1 | 3 |
| time_punch_events | 1 | 3 |

## Missing Tables

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
- **Export UTC:** 2026-02-28T21:34:21Z
- **Created:** already existed
