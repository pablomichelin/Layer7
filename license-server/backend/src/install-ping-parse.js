const { createHttpError } = require('./http-error');
const { normalizeStoredHardwareId } = require('./crud-validation');

const INSTALL_PING_EVENTS = new Set(['install', 'upgrade', 'heartbeat']);
const HARDWARE_ID_PATTERN = /^[a-f0-9]{64}$/i;
const LICENSE_KEY_PATTERN = /^[a-f0-9]{32}$/i;
const INSTALL_ID_PATTERN = /^[a-f0-9-]{8,64}$/i;
const IFACE_NAME_PATTERN = /^[a-zA-Z0-9_.-]{1,32}$/;
const IPV4_PATTERN = /^(?:\d{1,3}\.){3}\d{1,3}$/;
const IPV6_PATTERN = /^[0-9a-f:.]{2,45}$/i;
const MAX_INTERFACES = 32;
const MAX_PAYLOAD_CHARS = 16384;

const ALLOWED_FIELDS = [
  'hardware_id',
  'install_id',
  'event',
  'package_version',
  'hostname',
  'domain',
  'fqdn',
  'pfsense_version',
  'pfsense_version_patch',
  'platform',
  'uniqueid',
  'system_serial',
  'os_release',
  'hw_model',
  'ncpu',
  'mem_mb',
  'wan_ipv4',
  'wan_ipv6',
  'gateway_v4',
  'license_key',
  'interfaces',
];

function ensureObject(payload, message = 'Payload invalido.') {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    throw createHttpError(400, message);
  }
  return payload;
}

function rejectUnexpectedFields(payload, allowedFields) {
  const unexpected = Object.keys(payload).filter((field) => !allowedFields.includes(field));
  if (unexpected.length > 0) {
    throw createHttpError(400, `Payload contem campos nao permitidos: ${unexpected.join(', ')}`);
  }
}

function optionalText(value, fieldName, maxLength) {
  if (value === undefined || value === null || value === '') {
    return null;
  }
  if (typeof value !== 'string') {
    throw createHttpError(400, `${fieldName} invalido.`);
  }
  const normalized = value.trim();
  if (!normalized) {
    return null;
  }
  if (normalized.length > maxLength) {
    throw createHttpError(400, `${fieldName} excede o tamanho maximo permitido.`);
  }
  return normalized;
}

function optionalInt(value, fieldName, min, max) {
  if (value === undefined || value === null || value === '') {
    return null;
  }
  const n = typeof value === 'number' ? value : Number.parseInt(String(value), 10);
  if (!Number.isInteger(n) || n < min || n > max) {
    throw createHttpError(400, `${fieldName} invalido.`);
  }
  return n;
}

function isValidIpv4(value) {
  if (!IPV4_PATTERN.test(value)) {
    return false;
  }
  return value.split('.').every((octet) => {
    const n = Number.parseInt(octet, 10);
    return n >= 0 && n <= 255;
  });
}

function optionalIp(value, fieldName, { v4Only = false } = {}) {
  const text = optionalText(value, fieldName, 45);
  if (!text) {
    return null;
  }
  if (isValidIpv4(text)) {
    return text;
  }
  if (!v4Only && IPV6_PATTERN.test(text) && text.includes(':')) {
    return text.toLowerCase();
  }
  throw createHttpError(400, `${fieldName} invalido.`);
}

function parseInterfaces(raw) {
  if (raw === undefined || raw === null) {
    return [];
  }
  if (!Array.isArray(raw)) {
    throw createHttpError(400, 'interfaces invalido.');
  }
  if (raw.length > MAX_INTERFACES) {
    throw createHttpError(400, 'interfaces excede o tamanho maximo permitido.');
  }

  return raw.map((entry, index) => {
    const iface = ensureObject(entry, `interfaces[${index}] invalido.`);
    rejectUnexpectedFields(iface, ['id', 'real', 'descr', 'ipv4', 'ipv6']);
    const id = optionalText(iface.id, `interfaces[${index}].id`, 32);
    const real = optionalText(iface.real, `interfaces[${index}].real`, 32);
    if (id && !IFACE_NAME_PATTERN.test(id)) {
      throw createHttpError(400, `interfaces[${index}].id invalido.`);
    }
    if (real && !IFACE_NAME_PATTERN.test(real)) {
      throw createHttpError(400, `interfaces[${index}].real invalido.`);
    }
    return {
      id,
      real,
      descr: optionalText(iface.descr, `interfaces[${index}].descr`, 64),
      ipv4: optionalIp(iface.ipv4, `interfaces[${index}].ipv4`, { v4Only: true }),
      ipv6: optionalIp(iface.ipv6, `interfaces[${index}].ipv6`),
    };
  });
}

function parseInstallPingPayload(body, { rawLength } = {}) {
  if (typeof rawLength === 'number' && rawLength > MAX_PAYLOAD_CHARS) {
    throw createHttpError(400, 'Payload excede o tamanho maximo permitido.');
  }

  const payload = ensureObject(body);
  rejectUnexpectedFields(payload, ALLOWED_FIELDS);

  const hardwareId = normalizeStoredHardwareId(
    typeof payload.hardware_id === 'string' ? payload.hardware_id : ''
  );
  if (!hardwareId || !HARDWARE_ID_PATTERN.test(hardwareId)) {
    throw createHttpError(400, 'hardware_id invalido.');
  }

  const event = optionalText(payload.event, 'event', 16);
  if (!event || !INSTALL_PING_EVENTS.has(event)) {
    throw createHttpError(400, 'event invalido.');
  }

  let licenseKey = optionalText(payload.license_key, 'license_key', 32);
  if (licenseKey) {
    licenseKey = licenseKey.toLowerCase();
    if (!LICENSE_KEY_PATTERN.test(licenseKey)) {
      throw createHttpError(400, 'license_key invalida.');
    }
  }

  let installId = optionalText(payload.install_id, 'install_id', 64);
  if (installId && !INSTALL_ID_PATTERN.test(installId)) {
    throw createHttpError(400, 'install_id invalido.');
  }

  return {
    hardwareId,
    installId,
    event,
    packageVersion: optionalText(payload.package_version, 'package_version', 32),
    hostname: optionalText(payload.hostname, 'hostname', 255),
    domain: optionalText(payload.domain, 'domain', 255),
    fqdn: optionalText(payload.fqdn, 'fqdn', 255),
    pfsenseVersion: optionalText(payload.pfsense_version, 'pfsense_version', 64),
    pfsenseVersionPatch: optionalText(payload.pfsense_version_patch, 'pfsense_version_patch', 32),
    platform: optionalText(payload.platform, 'platform', 64),
    uniqueid: optionalText(payload.uniqueid, 'uniqueid', 128),
    systemSerial: optionalText(payload.system_serial, 'system_serial', 128),
    osRelease: optionalText(payload.os_release, 'os_release', 64),
    hwModel: optionalText(payload.hw_model, 'hw_model', 128),
    ncpu: optionalInt(payload.ncpu, 'ncpu', 1, 256),
    memMb: optionalInt(payload.mem_mb, 'mem_mb', 1, 1048576),
    wanIpv4: optionalIp(payload.wan_ipv4, 'wan_ipv4', { v4Only: true }),
    wanIpv6: optionalIp(payload.wan_ipv6, 'wan_ipv6'),
    gatewayV4: optionalIp(payload.gateway_v4, 'gateway_v4', { v4Only: true }),
    licenseKey,
    interfaces: parseInterfaces(payload.interfaces),
  };
}

function shouldSkipHeartbeatLog(previousSeenAt, event, now = new Date()) {
  if (event !== 'heartbeat' || !previousSeenAt) {
    return false;
  }
  const previous = previousSeenAt instanceof Date ? previousSeenAt : new Date(previousSeenAt);
  if (Number.isNaN(previous.getTime())) {
    return false;
  }
  return (now.getTime() - previous.getTime()) < (6 * 60 * 60 * 1000);
}

function parseInstallationsListQuery(query = {}) {
  const pageRaw = Number.parseInt(String(query.page || '1'), 10);
  const limitRaw = Number.parseInt(String(query.limit || '20'), 10);
  const page = Number.isInteger(pageRaw) && pageRaw > 0 ? pageRaw : 1;
  const limit = Number.isInteger(limitRaw) ? Math.min(100, Math.max(1, limitRaw)) : 20;

  let licensed;
  if (query.licensed === 'yes' || query.licensed === 'true') {
    licensed = true;
  } else if (query.licensed === 'no' || query.licensed === 'false') {
    licensed = false;
  }

  let staleDays;
  if (query.stale_days !== undefined && query.stale_days !== '') {
    staleDays = Number.parseInt(String(query.stale_days), 10);
    if (!Number.isInteger(staleDays) || staleDays < 1 || staleDays > 365) {
      throw createHttpError(400, 'stale_days invalido.');
    }
  }

  const search = typeof query.search === 'string' ? query.search.trim() : '';
  const hardwareId = normalizeStoredHardwareId(
    typeof query.hardware_id === 'string' ? query.hardware_id : ''
  );

  return {
    page,
    limit,
    offset: (page - 1) * limit,
    licensed,
    staleDays,
    search: search || undefined,
    hardwareId,
  };
}

module.exports = {
  MAX_PAYLOAD_CHARS,
  parseInstallPingPayload,
  parseInstallationsListQuery,
  shouldSkipHeartbeatLog,
};
