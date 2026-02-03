-- Migration: Add product_daily_sales table and expected_delivery_date to orders
-- Run this to add daily sales tracking and order delivery date tracking

-- Create product_daily_sales table for historical daily sales tracking
CREATE TABLE IF NOT EXISTS product_daily_sales (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    sale_date DATE NOT NULL,
    quantity_sold DECIMAL(10,3) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, product_id, sale_date),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Add index for fast lookups by store, product, and date range
CREATE INDEX IF NOT EXISTS idx_product_daily_sales_store_product_date 
    ON product_daily_sales(store_id, product_id, sale_date);

-- Add expected_delivery_date to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS expected_delivery_date DATE;

-- Update trigger for product_daily_sales
CREATE TRIGGER update_product_daily_sales_updated_at 
    BEFORE UPDATE ON product_daily_sales 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();
