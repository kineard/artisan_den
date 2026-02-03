-- Migration script to add rating and new inventory fields
-- Run this if you already have the inventory tables

-- Add rating column to vendors if it doesn't exist
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS rating INTEGER DEFAULT 3;
ALTER TABLE vendors ADD CONSTRAINT IF NOT EXISTS vendors_rating_check CHECK (rating >= 1 AND rating <= 5);
UPDATE vendors SET rating = 3 WHERE rating IS NULL;

-- Add new columns to inventory if they don't exist
ALTER TABLE inventory ADD COLUMN IF NOT EXISTS avg_daily_usage DECIMAL(10,3) DEFAULT 0;
ALTER TABLE inventory ADD COLUMN IF NOT EXISTS days_of_stock INTEGER DEFAULT 7;
ALTER TABLE inventory ADD COLUMN IF NOT EXISTS substitution_product_id INTEGER;
ALTER TABLE inventory ADD CONSTRAINT IF NOT EXISTS inventory_substitution_fk FOREIGN KEY (substitution_product_id) REFERENCES products(id) ON DELETE SET NULL;

-- Update existing inventory to have default days_of_stock
UPDATE inventory SET days_of_stock = 7 WHERE days_of_stock IS NULL OR days_of_stock = 0;
