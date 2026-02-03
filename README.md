# artisan_den

KPI dashboard and inventory management (Inventory V2: daily sales, on-hand, orders).

- **Dashboard**: Bank, safe, sales, COGS, labor, overhead; profit and labor %
- **Inventory & Reorder**: Products by store, 7/30-day averages, ROP, suggested order, orders
- **Daily On-Hand**: Enter sales per day; On Hand is computed (starting qty + received − sales)
- **Charts**: Single-product view (On Hand, Sales, Purchases, Price) when you click a SKU

Setup: see `setup-db.sh` / `setup-local.sh`. PHP + PostgreSQL.
