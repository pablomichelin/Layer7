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

CREATE INDEX idx_licenses_key ON licenses(license_key);
CREATE INDEX idx_licenses_status ON licenses(status);
CREATE INDEX idx_licenses_customer ON licenses(customer_id);
CREATE INDEX idx_activations_license ON activations_log(license_id);
CREATE INDEX idx_activations_created ON activations_log(created_at);
