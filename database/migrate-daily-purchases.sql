-- Manual daily purchases (transfers, cash purchases, etc.)
-- Received-from-orders are in orders (status RECEIVED, received_date).
-- This table stores manual purchase entries per product/date.
CREATE TABLE IF NOT EXISTS product_daily_purchases (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    purchase_date DATE NOT NULL,
    quantity_received DECIMAL(10,3) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, product_id, purchase_date),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_product_daily_purchases_store_product_date
    ON product_daily_purchases(store_id, product_id, purchase_date);
