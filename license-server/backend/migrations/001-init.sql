CREATE TABLE admins (
    id          SERIAL PRIMARY KEY,
    email       VARCHAR(255) UNIQUE NOT NULL,
    name        VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE customers (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255),
    phone       VARCHAR(50),
    notes       TEXT,
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE licenses (
    id          SERIAL PRIMARY KEY,
    customer_id INTEGER REFERENCES customers(id) ON DELETE RESTRICT,
    hardware_id VARCHAR(64),
    license_key VARCHAR(64) UNIQUE NOT NULL,
    expiry      DATE NOT NULL,
    features    VARCHAR(64) DEFAULT 'full',
    status      VARCHAR(20) DEFAULT 'active'
                CHECK (status IN ('active', 'revoked', 'expired')),
    activated_at TIMESTAMP,
    revoked_at  TIMESTAMP,
    notes       TEXT,
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE activations_log (
    id          SERIAL PRIMARY KEY,
    license_id  INTEGER REFERENCES licenses(id) ON DELETE CASCADE,
    hardware_id VARCHAR(64),
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(255),
    result      VARCHAR(20) CHECK (result IN ('success', 'fail', 'revoked')),
    error_message TEXT,
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE admin_sessions (
    id          SERIAL PRIMARY KEY,
    admin_id    INTEGER NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
    session_token_hash VARCHAR(64) UNIQUE NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
    expires_at  TIMESTAMP NOT NULL,
    last_seen_at TIMESTAMP NOT NULL DEFAULT NOW(),
    revoked_at  TIMESTAMP,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(255)
);

CREATE TABLE admin_audit_log (
    id          SERIAL PRIMARY KEY,
    component   VARCHAR(64) NOT NULL,
    event_type  VARCHAR(64) NOT NULL,
    actor_admin_id INTEGER REFERENCES admins(id) ON DELETE SET NULL,
    actor_identifier VARCHAR(255),
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(255),
    route       VARCHAR(255),
    result      VARCHAR(32) NOT NULL,
    reason      VARCHAR(255),
    metadata    JSONB,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE admin_login_guards (
    id          SERIAL PRIMARY KEY,
    scope_type  VARCHAR(32) NOT NULL,
    scope_key   VARCHAR(255) NOT NULL,
    failure_count INTEGER NOT NULL DEFAULT 0,
    first_failure_at TIMESTAMPTZ,
    last_failure_at TIMESTAMPTZ,
    locked_until TIMESTAMPTZ,
    last_success_at TIMESTAMPTZ,
    UNIQUE(scope_type, scope_key)
);

CREATE INDEX idx_licenses_key ON licenses(license_key);
CREATE INDEX idx_licenses_status ON licenses(status);
CREATE INDEX idx_licenses_customer ON licenses(customer_id);
CREATE INDEX idx_activations_license ON activations_log(license_id);
CREATE INDEX idx_activations_created ON activations_log(created_at);
CREATE INDEX idx_admin_sessions_admin ON admin_sessions(admin_id);
CREATE INDEX idx_admin_sessions_expires ON admin_sessions(expires_at);
CREATE INDEX idx_admin_sessions_revoked ON admin_sessions(revoked_at);
CREATE INDEX idx_admin_audit_log_created_at ON admin_audit_log(created_at);
CREATE INDEX idx_admin_audit_log_event_type ON admin_audit_log(event_type);
CREATE INDEX idx_admin_audit_log_actor_admin ON admin_audit_log(actor_admin_id);
CREATE INDEX idx_admin_login_guards_locked_until ON admin_login_guards(locked_until);
