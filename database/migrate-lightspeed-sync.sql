-- Lightspeed X integration metadata + mapping tables (idempotent)

CREATE TABLE IF NOT EXISTS integration_sync_runs (
    id BIGSERIAL PRIMARY KEY,
    provider VARCHAR(40) NOT NULL DEFAULT 'lightspeed_x',
    entity_name VARCHAR(80) NOT NULL,
    mode_name VARCHAR(40) NOT NULL DEFAULT 'manual',
    status VARCHAR(20) NOT NULL DEFAULT 'RUNNING',
    records_seen INTEGER NOT NULL DEFAULT 0,
    records_upserted INTEGER NOT NULL DEFAULT 0,
    records_failed INTEGER NOT NULL DEFAULT 0,
    message TEXT,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    started_by VARCHAR(120) NULL
);

-- Keep status wide enough for values like COMPLETED_WITH_ERRORS
ALTER TABLE integration_sync_runs
    ALTER COLUMN status TYPE VARCHAR(40);

CREATE INDEX IF NOT EXISTS idx_sync_runs_provider_entity_started
    ON integration_sync_runs (provider, entity_name, started_at DESC);

CREATE TABLE IF NOT EXISTS integration_sync_errors (
    id BIGSERIAL PRIMARY KEY,
    sync_run_id BIGINT NOT NULL REFERENCES integration_sync_runs(id) ON DELETE CASCADE,
    external_id VARCHAR(255) NULL,
    error_code VARCHAR(80) NULL,
    error_message TEXT NOT NULL,
    payload_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sync_errors_run
    ON integration_sync_errors (sync_run_id, created_at DESC);

CREATE TABLE IF NOT EXISTS integration_sync_checkpoints (
    provider VARCHAR(40) NOT NULL DEFAULT 'lightspeed_x',
    entity_name VARCHAR(80) NOT NULL,
    checkpoint_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (provider, entity_name)
);

CREATE TABLE IF NOT EXISTS lightspeed_categories (
    id BIGSERIAL PRIMARY KEY,
    external_id VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    raw_payload_json TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lightspeed_product_map (
    id BIGSERIAL PRIMARY KEY,
    external_id VARCHAR(255) NOT NULL UNIQUE,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    raw_payload_json TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_lightspeed_product_map_product_id
    ON lightspeed_product_map (product_id);

CREATE TABLE IF NOT EXISTS lightspeed_product_category_map (
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    lightspeed_category_id BIGINT NOT NULL REFERENCES lightspeed_categories(id) ON DELETE CASCADE,
    PRIMARY KEY (product_id, lightspeed_category_id)
);

CREATE TABLE IF NOT EXISTS lightspeed_supplier_map (
    id BIGSERIAL PRIMARY KEY,
    external_id VARCHAR(255) NOT NULL UNIQUE,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    raw_payload_json TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_lightspeed_supplier_map_vendor_id
    ON lightspeed_supplier_map (vendor_id);

CREATE TABLE IF NOT EXISTS lightspeed_outlet_map (
    external_outlet_id VARCHAR(255) PRIMARY KEY,
    store_id INTEGER NULL REFERENCES stores(id) ON DELETE CASCADE,
    outlet_name VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Allow discovered outlets to exist before a local store mapping is assigned.
ALTER TABLE lightspeed_outlet_map
    ALTER COLUMN store_id DROP NOT NULL;

-- App-facing generic category tables (for downstream UI/report joins)
CREATE TABLE IF NOT EXISTS product_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    external_id VARCHAR(255) NULL,
    provider VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS product_category_map (
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    category_id INTEGER NOT NULL REFERENCES product_categories(id) ON DELETE CASCADE,
    PRIMARY KEY (product_id, category_id)
);
