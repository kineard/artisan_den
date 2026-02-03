-- Inventory Management Schema (PostgreSQL)
-- Run this to add inventory tables to existing database

-- Vendors table
CREATE TABLE IF NOT EXISTS vendors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    contact_name VARCHAR(100),
    phone VARCHAR(50),
    email VARCHAR(100),
    order_method VARCHAR(50), -- 'text', 'call', 'site', 'text/call'
    cutoff_time VARCHAR(100),
    typical_lead_time VARCHAR(100),
    shipping_speed_notes TEXT,
    free_ship_threshold DECIMAL(10,2),
    account_info VARCHAR(255),
    password VARCHAR(255),
    notes TEXT,
    rating INTEGER DEFAULT 3 CHECK (rating >= 1 AND rating <= 5),
    is_preferred BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add rating column if table already exists
DO \$\$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='vendors' AND column_name='rating') THEN
        ALTER TABLE vendors ADD COLUMN rating INTEGER DEFAULT 3 CHECK (rating >= 1 AND rating <= 5);
    END IF;
END \$\$;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit_type VARCHAR(50) DEFAULT 'unit', -- 'unit', 'gram', 'box', etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inventory per store
CREATE TABLE IF NOT EXISTS inventory (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    on_hand DECIMAL(10,3) DEFAULT 0,
    reorder_point DECIMAL(10,3) DEFAULT 0,
    target_max DECIMAL(10,3) DEFAULT 0,
    avg_daily_usage DECIMAL(10,3) DEFAULT 0, -- Average units sold per day
    days_of_stock INTEGER DEFAULT 7, -- Target days of stock (default 7)
    vendor_id INTEGER,
    vendor_sku VARCHAR(100),
    vendor_link TEXT,
    lead_time_days INTEGER DEFAULT 0,
    unit_cost DECIMAL(10,2) DEFAULT 0,
    substitution_product_id INTEGER, -- Product to order if this one is unavailable
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, product_id),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
    FOREIGN KEY (substitution_product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Add new columns if table already exists
DO \$\$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='inventory' AND column_name='avg_daily_usage') THEN
        ALTER TABLE inventory ADD COLUMN avg_daily_usage DECIMAL(10,3) DEFAULT 0;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='inventory' AND column_name='days_of_stock') THEN
        ALTER TABLE inventory ADD COLUMN days_of_stock INTEGER DEFAULT 7;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='inventory' AND column_name='substitution_product_id') THEN
        ALTER TABLE inventory ADD COLUMN substitution_product_id INTEGER REFERENCES products(id) ON DELETE SET NULL;
    END IF;
END \$\$;

-- Orders tracking
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL,
    vendor_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_cost DECIMAL(10,2) DEFAULT 0,
    total_cost DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'REQUESTED', -- 'REQUESTED', 'ORDERED', 'RECEIVED', 'STOCKED'
    order_date DATE,
    received_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Update triggers
CREATE TRIGGER update_vendors_updated_at BEFORE UPDATE ON vendors FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_products_updated_at BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_inventory_updated_at BEFORE UPDATE ON inventory FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
