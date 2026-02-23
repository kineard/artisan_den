# artisan_den

Launch 1 scope is locked to KPI, Inventory/Reorder, and Time Clock for a multi-store business org.

- **Launch 1 Modules (locked):** KPI, Product Inventory/Reorder, Time Clock
- **Deferred (not in Launch 1):** Full POS checkout (cart, tenders, returns, receipts)
- **Dashboard (KPI):** Bank, safe, sales, COGS, labor, overhead; profit and labor %
- **Inventory & Reorder:** Products by store, 7/30-day averages, ROP, suggested order, orders
- **Daily On-Hand:** Enter sales per day; On Hand is computed (starting qty + received - sales)
- **Charts:** Single-product view (On Hand, Sales, Purchases, Price) when you click a SKU

Scope details and non-negotiables: see `docs/LAUNCH1-SCOPE.md`.
Development workflow: see `docs/GIT-WORKFLOW.md`.

Setup: see `setup-db.sh` / `setup-local.sh`. PHP + PostgreSQL.

## Pre-Launch Reminder

- RBAC is currently dev-friendly and defaults to manager-level access when session role is not set.
- Before production launch, tighten this to strict role enforcement from authenticated session/login only.
- Verify all privileged actions (KPI write, Inventory write, Time Clock manager actions, payroll export) require explicit manager/admin role.
- Geofence go-live check: validate policy on real GPS-capable devices (not VPN/IP), confirm expected `warn/block` behavior, and set final store coordinates/radius.

See `docs/GO-LIVE-CHECKLIST.md` for the full pre-launch checklist.
