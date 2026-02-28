-- Artisan Den KPI System Database Schema (PostgreSQL)
CREATE TABLE IF NOT EXISTS stores (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS daily_kpis (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL,
    entry_date DATE NOT NULL,
    bank_balance DECIMAL(10,2) DEFAULT 0.00,
    safe_balance DECIMAL(10,2) DEFAULT 0.00,
    sales_today DECIMAL(10,2) DEFAULT 0.00,
    cogs_today DECIMAL(10,2) DEFAULT 0.00,
    labor_today DECIMAL(10,2) DEFAULT 0.00,
    avg_daily_overhead DECIMAL(10,2) DEFAULT 0.00,
    updated_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, entry_date),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS lightspeed_imports (
    id SERIAL PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    import_date DATE NOT NULL,
    records_imported INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO stores (name) VALUES ('23rd St'), ('Pier Park') ON CONFLICT (name) DO NOTHING;
CREATE OR REPLACE FUNCTION update_updated_at_column() RETURNS TRIGGER AS $$ BEGIN NEW.updated_at = CURRENT_TIMESTAMP; RETURN NEW; END; $$ language 'plpgsql';
DROP TRIGGER IF EXISTS update_daily_kpis_updated_at ON daily_kpis;
CREATE TRIGGER update_daily_kpis_updated_at BEFORE UPDATE ON daily_kpis FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
