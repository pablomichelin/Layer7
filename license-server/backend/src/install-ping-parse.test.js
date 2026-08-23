const test = require('node:test');
const assert = require('node:assert/strict');
const {
  parseInstallPingPayload,
  parseInstallationsListQuery,
  shouldSkipHeartbeatLog,
} = require('./install-ping-parse');
const { inventorySnapshot, UPSERT_INSTALL_SQL } = require('./install-ping-schema');

const HW = 'a'.repeat(64);

function basePayload(overrides = {}) {
  return {
    hardware_id: HW,
    event: 'install',
    hostname: 'fw-matriz',
    domain: 'empresa.local',
    fqdn: 'fw-matriz.empresa.local',
    package_version: '1.9.71',
    wan_ipv4: '200.1.2.3',
    interfaces: [
      { id: 'wan', real: 'igb0', descr: 'WAN Vivo', ipv4: '200.1.2.3' },
      { id: 'lan', real: 'igb1', descr: 'Escritorio', ipv4: '192.168.10.1' },
    ],
    ...overrides,
  };
}

test('BG-162 parse: payload minimo e inventário', () => {
  const parsed = parseInstallPingPayload(basePayload());
  assert.equal(parsed.hardwareId, HW);
  assert.equal(parsed.event, 'install');
  assert.equal(parsed.hostname, 'fw-matriz');
  assert.equal(parsed.wanIpv4, '200.1.2.3');
  assert.equal(parsed.interfaces.length, 2);
  assert.equal(parsed.interfaces[1].descr, 'Escritorio');
  const snap = inventorySnapshot(parsed);
  assert.equal(snap.fqdn, 'fw-matriz.empresa.local');
});

test('BG-162 parse: rejeita campo extra e hardware_id curto', () => {
  assert.throws(
    () => parseInstallPingPayload(basePayload({ traffic: 'nope' })),
    /campos nao permitidos/
  );
  assert.throws(
    () => parseInstallPingPayload(basePayload({ hardware_id: 'abc' })),
    /hardware_id invalido/
  );
  assert.throws(
    () => parseInstallPingPayload(basePayload({ event: 'boot' })),
    /event invalido/
  );
});

test('BG-162 parse: IPs e teto de interfaces', () => {
  assert.throws(
    () => parseInstallPingPayload(basePayload({ wan_ipv4: '999.1.1.1' })),
    /wan_ipv4 invalido/
  );
  const tooMany = Array.from({ length: 33 }, (_, i) => ({ id: `opt${i}` }));
  assert.throws(
    () => parseInstallPingPayload(basePayload({ interfaces: tooMany })),
    /interfaces excede/
  );
});

test('BG-162 parse: license_key opcional só se 32 hex', () => {
  const parsed = parseInstallPingPayload(basePayload({ license_key: 'b'.repeat(32) }));
  assert.equal(parsed.licenseKey, 'b'.repeat(32));
  assert.throws(
    () => parseInstallPingPayload(basePayload({ license_key: 'not-a-key' })),
    /license_key invalida/
  );
});

test('BG-162 parse: payload acima de 16KiB', () => {
  assert.throws(
    () => parseInstallPingPayload(basePayload(), { rawLength: 20000 }),
    /tamanho maximo/
  );
});

test('BG-162: heartbeat recente nao infla o log', () => {
  const recent = new Date(Date.now() - 60 * 60 * 1000);
  const old = new Date(Date.now() - 7 * 60 * 60 * 1000);
  assert.equal(shouldSkipHeartbeatLog(recent, 'heartbeat'), true);
  assert.equal(shouldSkipHeartbeatLog(old, 'heartbeat'), false);
  assert.equal(shouldSkipHeartbeatLog(recent, 'install'), false);
  assert.equal(shouldSkipHeartbeatLog(null, 'heartbeat'), false);
});

test('BG-162 lista: filtros licensed/stale/search', () => {
  const parsed = parseInstallationsListQuery({
    licensed: 'no',
    stale_days: '7',
    search: 'empresa.local',
    page: '2',
    limit: '10',
  });
  assert.equal(parsed.licensed, false);
  assert.equal(parsed.staleDays, 7);
  assert.equal(parsed.search, 'empresa.local');
  assert.equal(parsed.offset, 10);
});

test('BG-162 schema: upsert por hardware_id', () => {
  assert.match(UPSERT_INSTALL_SQL, /ON CONFLICT \(hardware_id\) DO UPDATE/);
});
