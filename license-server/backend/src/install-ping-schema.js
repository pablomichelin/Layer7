const pool = require('./db');

async function ensureInstallPingSchema() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS install_instances (
      id SERIAL PRIMARY KEY,
      hardware_id VARCHAR(64) NOT NULL UNIQUE,
      install_id VARCHAR(64),
      hostname VARCHAR(255),
      domain VARCHAR(255),
      fqdn VARCHAR(255),
      package_version VARCHAR(32),
      pfsense_version VARCHAR(64),
      pfsense_version_patch VARCHAR(32),
      platform VARCHAR(64),
      uniqueid VARCHAR(128),
      system_serial VARCHAR(128),
      os_release VARCHAR(64),
      hw_model VARCHAR(128),
      ncpu INTEGER,
      mem_mb INTEGER,
      wan_ipv4 VARCHAR(45),
      wan_ipv6 VARCHAR(45),
      gateway_v4 VARCHAR(45),
      license_key VARCHAR(64),
      egress_ip VARCHAR(45),
      last_event VARCHAR(16),
      inventory JSONB,
      last_user_agent VARCHAR(255),
      first_seen_at TIMESTAMP DEFAULT NOW(),
      last_seen_at TIMESTAMP DEFAULT NOW()
    )
  `);

  await pool.query(`
    CREATE TABLE IF NOT EXISTS install_pings_log (
      id SERIAL PRIMARY KEY,
      instance_id INTEGER REFERENCES install_instances(id) ON DELETE CASCADE,
      hardware_id VARCHAR(64),
      event VARCHAR(16),
      package_version VARCHAR(32),
      egress_ip VARCHAR(45),
      user_agent VARCHAR(255),
      result VARCHAR(20),
      error_message TEXT,
      created_at TIMESTAMP DEFAULT NOW()
    )
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_install_instances_last_seen
      ON install_instances(last_seen_at DESC)
  `);
  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_install_instances_fqdn
      ON install_instances(fqdn)
  `);
  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_install_pings_log_instance
      ON install_pings_log(instance_id)
  `);
  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_install_pings_log_created
      ON install_pings_log(created_at)
  `);
}

const UPSERT_INSTALL_SQL = `
INSERT INTO install_instances (
  hardware_id, install_id, hostname, domain, fqdn, package_version,
  pfsense_version, pfsense_version_patch, platform, uniqueid, system_serial,
  os_release, hw_model, ncpu, mem_mb, wan_ipv4, wan_ipv6, gateway_v4,
  license_key, egress_ip, last_event, inventory, last_user_agent, last_seen_at
) VALUES (
  $1, $2, $3, $4, $5, $6,
  $7, $8, $9, $10, $11,
  $12, $13, $14, $15, $16, $17, $18,
  $19, $20, $21, $22::jsonb, $23, NOW()
)
ON CONFLICT (hardware_id) DO UPDATE SET
  install_id = COALESCE(EXCLUDED.install_id, install_instances.install_id),
  hostname = EXCLUDED.hostname,
  domain = EXCLUDED.domain,
  fqdn = EXCLUDED.fqdn,
  package_version = EXCLUDED.package_version,
  pfsense_version = EXCLUDED.pfsense_version,
  pfsense_version_patch = EXCLUDED.pfsense_version_patch,
  platform = EXCLUDED.platform,
  uniqueid = COALESCE(EXCLUDED.uniqueid, install_instances.uniqueid),
  system_serial = COALESCE(EXCLUDED.system_serial, install_instances.system_serial),
  os_release = EXCLUDED.os_release,
  hw_model = EXCLUDED.hw_model,
  ncpu = EXCLUDED.ncpu,
  mem_mb = EXCLUDED.mem_mb,
  wan_ipv4 = EXCLUDED.wan_ipv4,
  wan_ipv6 = EXCLUDED.wan_ipv6,
  gateway_v4 = EXCLUDED.gateway_v4,
  license_key = COALESCE(EXCLUDED.license_key, install_instances.license_key),
  egress_ip = EXCLUDED.egress_ip,
  last_event = EXCLUDED.last_event,
  inventory = EXCLUDED.inventory,
  last_user_agent = EXCLUDED.last_user_agent,
  last_seen_at = NOW()
RETURNING *
`;

function inventorySnapshot(parsed) {
  return {
    interfaces: parsed.interfaces,
    hostname: parsed.hostname,
    domain: parsed.domain,
    fqdn: parsed.fqdn,
    package_version: parsed.packageVersion,
    pfsense_version: parsed.pfsenseVersion,
    platform: parsed.platform,
    uniqueid: parsed.uniqueid,
    system_serial: parsed.systemSerial,
    wan_ipv4: parsed.wanIpv4,
    wan_ipv6: parsed.wanIpv6,
    gateway_v4: parsed.gatewayV4,
    hw_model: parsed.hwModel,
    ncpu: parsed.ncpu,
    mem_mb: parsed.memMb,
  };
}

function upsertParams(parsed, egressIp, userAgent) {
  return [
    parsed.hardwareId,
    parsed.installId,
    parsed.hostname,
    parsed.domain,
    parsed.fqdn,
    parsed.packageVersion,
    parsed.pfsenseVersion,
    parsed.pfsenseVersionPatch,
    parsed.platform,
    parsed.uniqueid,
    parsed.systemSerial,
    parsed.osRelease,
    parsed.hwModel,
    parsed.ncpu,
    parsed.memMb,
    parsed.wanIpv4,
    parsed.wanIpv6,
    parsed.gatewayV4,
    parsed.licenseKey,
    egressIp,
    parsed.event,
    JSON.stringify(inventorySnapshot(parsed)),
    userAgent ? String(userAgent).slice(0, 255) : null,
  ];
}

module.exports = {
  UPSERT_INSTALL_SQL,
  ensureInstallPingSchema,
  inventorySnapshot,
  upsertParams,
};
