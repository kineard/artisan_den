-- Time Clock module schema (PostgreSQL)

CREATE TABLE IF NOT EXISTS employees (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    role_name VARCHAR(100) DEFAULT 'Employee',
    email VARCHAR(150) DEFAULT NULL,
    pin_hash VARCHAR(255) NOT NULL,
    hourly_rate_cents INTEGER NOT NULL DEFAULT 0,
    burden_percent NUMERIC(8,4) NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employee_locations (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (employee_id, store_id)
);

CREATE TABLE IF NOT EXISTS time_shifts (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    clock_in_utc TIMESTAMPTZ NOT NULL,
    clock_out_utc TIMESTAMPTZ DEFAULT NULL,
    clock_in_ip VARCHAR(64) DEFAULT NULL,
    clock_out_ip VARCHAR(64) DEFAULT NULL,
    clock_in_user_agent TEXT DEFAULT NULL,
    clock_out_user_agent TEXT DEFAULT NULL,
    clock_in_note TEXT DEFAULT NULL,
    clock_out_note TEXT DEFAULT NULL,
    gps_lat NUMERIC(10,7) DEFAULT NULL,
    gps_lng NUMERIC(10,7) DEFAULT NULL,
    gps_accuracy_m NUMERIC(10,2) DEFAULT NULL,
    gps_captured BOOLEAN NOT NULL DEFAULT FALSE,
    gps_status VARCHAR(20) NOT NULL DEFAULT 'unavailable', -- ok | denied | unavailable
    geofence_pass BOOLEAN DEFAULT NULL,
    geofence_distance_m NUMERIC(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS time_punch_events (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    shift_id INTEGER REFERENCES time_shifts(id) ON DELETE SET NULL,
    event_type VARCHAR(20) NOT NULL, -- CLOCK_IN | CLOCK_OUT | BREAK_START | BREAK_END | EDIT_REQUEST
    event_utc TIMESTAMPTZ NOT NULL,
    event_ip VARCHAR(64) DEFAULT NULL,
    event_user_agent TEXT DEFAULT NULL,
    gps_lat NUMERIC(10,7) DEFAULT NULL,
    gps_lng NUMERIC(10,7) DEFAULT NULL,
    gps_accuracy_m NUMERIC(10,2) DEFAULT NULL,
    gps_captured BOOLEAN NOT NULL DEFAULT FALSE,
    gps_status VARCHAR(20) NOT NULL DEFAULT 'unavailable',
    geofence_pass BOOLEAN DEFAULT NULL,
    geofence_distance_m NUMERIC(10,2) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_by VARCHAR(30) NOT NULL DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS timeclock_settings (
    id SERIAL PRIMARY KEY,
    scope VARCHAR(20) NOT NULL DEFAULT 'global', -- global | store
    store_id INTEGER DEFAULT NULL REFERENCES stores(id) ON DELETE CASCADE,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (scope, store_id, setting_key)
);

CREATE TABLE IF NOT EXISTS timeclock_audit_log (
    id SERIAL PRIMARY KEY,
    store_id INTEGER DEFAULT NULL REFERENCES stores(id) ON DELETE SET NULL,
    employee_id INTEGER DEFAULT NULL REFERENCES employees(id) ON DELETE SET NULL,
    actor_name VARCHAR(120) NOT NULL,
    action_type VARCHAR(80) NOT NULL,
    details_json TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS timeclock_kiosk_sync_log (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    employee_id INTEGER DEFAULT NULL REFERENCES employees(id) ON DELETE SET NULL,
    device_id VARCHAR(120) NOT NULL DEFAULT 'unknown',
    punch_type VARCHAR(12) NOT NULL, -- in | out
    sync_status VARCHAR(20) NOT NULL, -- success | failed
    result_message TEXT DEFAULT NULL,
    payload_json TEXT DEFAULT NULL,
    resolution_status VARCHAR(20) NOT NULL DEFAULT 'OPEN', -- OPEN | RESOLVED | IGNORED
    resolved_at TIMESTAMP DEFAULT NULL,
    resolved_by VARCHAR(120) DEFAULT NULL,
    resolution_note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS timeclock_edit_requests (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    shift_id INTEGER DEFAULT NULL REFERENCES time_shifts(id) ON DELETE SET NULL,
    request_type VARCHAR(30) NOT NULL, -- MISS_CLOCK_IN | MISS_CLOCK_OUT | ADJUST_SHIFT
    requested_clock_in_utc TIMESTAMPTZ DEFAULT NULL,
    requested_clock_out_utc TIMESTAMPTZ DEFAULT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING', -- PENDING | APPROVED | DENIED
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP DEFAULT NULL,
    reviewed_by VARCHAR(120) DEFAULT NULL,
    manager_note TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS timeclock_schedule_shifts (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    role_name VARCHAR(100) DEFAULT 'Employee',
    start_utc TIMESTAMPTZ NOT NULL,
    end_utc TIMESTAMPTZ NOT NULL,
    break_minutes INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', -- DRAFT | PUBLISHED
    published_at TIMESTAMP DEFAULT NULL,
    last_modified_by VARCHAR(120) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS timeclock_schedule_weeks (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', -- DRAFT | PUBLISHED
    published_at TIMESTAMP DEFAULT NULL,
    published_by VARCHAR(120) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, week_start_date)
);

CREATE TABLE IF NOT EXISTS timeclock_payroll_periods (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    period_start_date DATE NOT NULL,
    period_end_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN', -- OPEN | PROCESSED | LOCKED
    processed_at TIMESTAMP DEFAULT NULL,
    processed_by VARCHAR(120) DEFAULT NULL,
    locked_at TIMESTAMP DEFAULT NULL,
    locked_by VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, period_start_date, period_end_date)
);

CREATE TABLE IF NOT EXISTS timeclock_payroll_runs (
    id SERIAL PRIMARY KEY,
    payroll_period_id INTEGER NOT NULL REFERENCES timeclock_payroll_periods(id) ON DELETE CASCADE,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    regular_minutes INTEGER NOT NULL DEFAULT 0,
    overtime_minutes INTEGER NOT NULL DEFAULT 0,
    pto_minutes INTEGER NOT NULL DEFAULT 0,
    gross_cents INTEGER NOT NULL DEFAULT 0,
    loaded_cost_cents INTEGER NOT NULL DEFAULT 0,
    effective_hourly_cents INTEGER NOT NULL DEFAULT 0,
    rates_snapshot_json TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (payroll_period_id, employee_id)
);

CREATE TABLE IF NOT EXISTS timeclock_pto_requests (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    request_start_date DATE NOT NULL,
    request_end_date DATE NOT NULL,
    requested_minutes INTEGER NOT NULL DEFAULT 0,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING', -- PENDING | APPROVED | DENIED
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP DEFAULT NULL,
    reviewed_by VARCHAR(120) DEFAULT NULL,
    manager_note TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS timeclock_pto_balances (
    id SERIAL PRIMARY KEY,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    accrued_minutes INTEGER NOT NULL DEFAULT 0,
    used_minutes INTEGER NOT NULL DEFAULT 0,
    pending_minutes INTEGER NOT NULL DEFAULT 0,
    available_minutes INTEGER NOT NULL DEFAULT 0,
    last_recalculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (employee_id, store_id)
);

CREATE TABLE IF NOT EXISTS timeclock_tasks (
    id SERIAL PRIMARY KEY,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    task_date DATE NOT NULL,
    schedule_shift_id INTEGER DEFAULT NULL REFERENCES timeclock_schedule_shifts(id) ON DELETE SET NULL,
    assigned_employee_id INTEGER DEFAULT NULL REFERENCES employees(id) ON DELETE SET NULL,
    title VARCHAR(200) NOT NULL,
    details TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN', -- OPEN | DONE
    completed_at TIMESTAMP DEFAULT NULL,
    completed_by VARCHAR(120) DEFAULT NULL,
    created_by VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_time_shifts_store_open ON time_shifts(store_id, clock_out_utc);
CREATE INDEX IF NOT EXISTS idx_time_shifts_employee_in ON time_shifts(employee_id, clock_in_utc DESC);
CREATE INDEX IF NOT EXISTS idx_time_punch_events_store_time ON time_punch_events(store_id, event_utc DESC);
CREATE INDEX IF NOT EXISTS idx_time_punch_events_employee_time ON time_punch_events(employee_id, event_utc DESC);
CREATE INDEX IF NOT EXISTS idx_timeclock_edit_requests_store_status ON timeclock_edit_requests(store_id, status, submitted_at DESC);
CREATE INDEX IF NOT EXISTS idx_timeclock_schedule_shifts_store_start ON timeclock_schedule_shifts(store_id, start_utc);
CREATE INDEX IF NOT EXISTS idx_timeclock_schedule_shifts_employee_range ON timeclock_schedule_shifts(employee_id, start_utc, end_utc);
CREATE INDEX IF NOT EXISTS idx_timeclock_payroll_periods_store_dates ON timeclock_payroll_periods(store_id, period_start_date, period_end_date);
CREATE INDEX IF NOT EXISTS idx_timeclock_payroll_runs_period_employee ON timeclock_payroll_runs(payroll_period_id, employee_id);
CREATE INDEX IF NOT EXISTS idx_timeclock_pto_requests_store_status ON timeclock_pto_requests(store_id, status, submitted_at DESC);
CREATE INDEX IF NOT EXISTS idx_timeclock_pto_balances_store_employee ON timeclock_pto_balances(store_id, employee_id);
CREATE INDEX IF NOT EXISTS idx_timeclock_tasks_store_date ON timeclock_tasks(store_id, task_date, status);
CREATE INDEX IF NOT EXISTS idx_timeclock_tasks_store_employee_date ON timeclock_tasks(store_id, assigned_employee_id, task_date);
CREATE INDEX IF NOT EXISTS idx_timeclock_kiosk_sync_store_time ON timeclock_kiosk_sync_log(store_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_timeclock_kiosk_sync_store_status ON timeclock_kiosk_sync_log(store_id, sync_status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_timeclock_kiosk_sync_store_resolution ON timeclock_kiosk_sync_log(store_id, resolution_status, created_at DESC);

ALTER TABLE timeclock_kiosk_sync_log ADD COLUMN IF NOT EXISTS resolution_status VARCHAR(20) NOT NULL DEFAULT 'OPEN';
ALTER TABLE timeclock_kiosk_sync_log ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP DEFAULT NULL;
ALTER TABLE timeclock_kiosk_sync_log ADD COLUMN IF NOT EXISTS resolved_by VARCHAR(120) DEFAULT NULL;
ALTER TABLE timeclock_kiosk_sync_log ADD COLUMN IF NOT EXISTS resolution_note TEXT DEFAULT NULL;

INSERT INTO timeclock_settings (scope, store_id, setting_key, setting_value)
VALUES
    ('global', NULL, 'rounding_minutes', '5'),
    ('global', NULL, 'ot_rule', 'weekly_over_40'),
    ('global', NULL, 'ot_multiplier', '1.5'),
    ('global', NULL, 'default_burden_percent', '12'),
    ('global', NULL, 'pto_accrual_method', 'per_hour_worked'),
    ('global', NULL, 'pto_minutes_per_hour', '1.15'),
    ('global', NULL, 'pto_exclude_overtime', '1'),
    ('global', NULL, 'pto_annual_cap_minutes', '2400'),
    ('global', NULL, 'pto_waiting_period_days', '0'),
    ('global', NULL, 'sick_policy_mode', 'per_30_hours'),
    ('global', NULL, 'sick_minutes_per_hour', '2.00'),
    ('global', NULL, 'holiday_policy_mode', 'fixed_paid_holidays'),
    ('global', NULL, 'holiday_pay_multiplier', '1.5'),
    ('global', NULL, 'break_mode', 'manual'),
    ('global', NULL, 'geofence_strict_default', '0'),
    ('global', NULL, 'geofence_enabled', '0'),
    ('global', NULL, 'geofence_lat', '0'),
    ('global', NULL, 'geofence_lng', '0'),
    ('global', NULL, 'geofence_radius_m', '120'),
    ('global', NULL, 'geofence_policy', 'warn'),
    ('global', NULL, 'geofence_allow_no_gps', '1'),
    ('global', NULL, 'kiosk_idle_seconds', '75'),
    ('global', NULL, 'kiosk_alert_open_failure_threshold', '3'),
    ('global', NULL, 'kiosk_alert_stale_minutes', '60'),
    ('global', NULL, 'no_show_grace_minutes', '15'),
    ('global', NULL, 'reminders_enabled', '1'),
    ('global', NULL, 'reminder_no_show_enabled', '1'),
    ('global', NULL, 'reminder_lead_minutes_csv', '60,720'),
    ('global', NULL, 'reminder_quiet_start', '22:00'),
    ('global', NULL, 'reminder_quiet_end', '06:00'),
    ('global', NULL, 'store_operating_hours_json', '{"mon":{"enabled":true,"open":"09:00","close":"21:00"},"tue":{"enabled":true,"open":"09:00","close":"21:00"},"wed":{"enabled":true,"open":"09:00","close":"21:00"},"thu":{"enabled":true,"open":"09:00","close":"21:00"},"fri":{"enabled":true,"open":"09:00","close":"21:00"},"sat":{"enabled":true,"open":"09:00","close":"21:00"},"sun":{"enabled":true,"open":"09:00","close":"21:00"}}'),
    ('global', NULL, 'require_network_to_punch', '1')
ON CONFLICT (scope, store_id, setting_key) DO NOTHING;
