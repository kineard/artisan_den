-- Daily inventory snapshots (on-hand per product/store/date) for tracking and extrapolated sales
CREATE TABLE IF NOT EXISTS inventory_snapshots (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    snapshot_date DATE NOT NULL,
    on_hand DECIMAL(10,3) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, product_id, snapshot_date),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_inventory_snapshots_store_date ON inventory_snapshots(store_id, snapshot_date);
CREATE INDEX IF NOT EXISTS idx_inventory_snapshots_product_date ON inventory_snapshots(product_id, snapshot_date);
