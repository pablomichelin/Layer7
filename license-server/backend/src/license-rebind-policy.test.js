const test = require('node:test');
const assert = require('node:assert/strict');

const {
  createLicenseRebindGuardError,
  parseRebindPayload,
} = require('./license-rebind-policy');

const HW_A = 'a'.repeat(64);
const HW_B = 'b'.repeat(64);

test('parseRebindPayload requires reason and defaults to unbind', () => {
  assert.deepEqual(
    parseRebindPayload({ reason: 'Troca de NIC no appliance lab' }),
    { reason: 'Troca de NIC no appliance lab', mode: 'unbind', newHardwareId: undefined }
  );
});

test('parseRebindPayload accepts set with hardware id', () => {
  const payload = parseRebindPayload({
    reason: 'Migracao controlada para novo hostuuid',
    mode: 'set',
    new_hardware_id: HW_B,
  });
  assert.equal(payload.mode, 'set');
  assert.equal(payload.newHardwareId, HW_B);
});

test('parseRebindPayload rejects short reason and invalid modes', () => {
  assert.throws(() => parseRebindPayload({ reason: 'curto' }), (e) => e.status === 400);
  assert.throws(
    () => parseRebindPayload({ reason: 'Motivo suficientemente longo', mode: 'magic' }),
    (e) => e.status === 400
  );
  assert.throws(
    () => parseRebindPayload({ reason: 'Motivo suficientemente longo', mode: 'set' }),
    (e) => e.status === 400
  );
});

test('createLicenseRebindGuardError blocks revoked and already unbound', () => {
  assert.equal(
    createLicenseRebindGuardError(
      { status: 'revoked', hardware_id: HW_A, expiry: '2027-01-01' },
      { mode: 'unbind' }
    ).status,
    409
  );
  assert.equal(
    createLicenseRebindGuardError(
      { status: 'active', hardware_id: null, expiry: '2027-01-01' },
      { mode: 'unbind' }
    ).status,
    409
  );
  assert.equal(
    createLicenseRebindGuardError(
      { status: 'active', hardware_id: HW_A, expiry: '2027-01-01' },
      { mode: 'unbind' }
    ),
    null
  );
  assert.equal(
    createLicenseRebindGuardError(
      { status: 'active', hardware_id: HW_A, expiry: '2027-01-01' },
      { mode: 'set', newHardwareId: HW_B }
    ),
    null
  );
});
