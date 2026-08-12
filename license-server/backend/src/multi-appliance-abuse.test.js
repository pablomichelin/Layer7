const test = require('node:test');
const assert = require('node:assert/strict');
const {
  evaluateMultiApplianceAbuse,
  buildAuthorizedHardwareSet,
} = require('./multi-appliance-abuse');

const HW_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const HW_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const HW_C = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

test('sem alerta com um único hardware_id', () => {
  const r = evaluateMultiApplianceAbuse({
    currentHardwareId: HW_A,
    observedHardwareIds: [HW_A, HW_A],
  });
  assert.equal(r.alert, false);
  assert.equal(r.reason, 'insufficient_distinct');
});

test('abuso: mesma chave em dois appliances sem rebind → alerta', () => {
  const r = evaluateMultiApplianceAbuse({
    currentHardwareId: HW_A,
    observedHardwareIds: [HW_A, HW_C],
    rebindHistory: [],
  });
  assert.equal(r.alert, true);
  assert.equal(r.reason, 'unexplained_multi_hardware');
  assert.deepEqual(r.unexplained_hardware_ids, [HW_C]);
});

test('rebind autorizado A→B: histórico A+B não gera falso positivo', () => {
  const r = evaluateMultiApplianceAbuse({
    currentHardwareId: HW_B,
    observedHardwareIds: [HW_A, HW_B],
    rebindHistory: [
      { previous_hardware_id: HW_A, new_hardware_id: HW_B },
    ],
  });
  assert.equal(r.alert, false);
  assert.equal(r.reason, 'explained_by_rebind_or_current_bind');
  assert.deepEqual(r.unexplained_hardware_ids, []);
});

test('após rebind A→B, terceiro appliance C → alerta', () => {
  const r = evaluateMultiApplianceAbuse({
    currentHardwareId: HW_B,
    observedHardwareIds: [HW_A, HW_B, HW_C],
    rebindHistory: [
      { previous_hardware_id: HW_A, new_hardware_id: HW_B },
    ],
  });
  assert.equal(r.alert, true);
  assert.deepEqual(r.unexplained_hardware_ids, [HW_C]);
});

test('buildAuthorizedHardwareSet inclui current + cadeia de rebind', () => {
  const set = buildAuthorizedHardwareSet(HW_B, [
    { previous_hardware_id: HW_A, new_hardware_id: HW_B },
  ]);
  assert.equal(set.has(HW_A), true);
  assert.equal(set.has(HW_B), true);
  assert.equal(set.has(HW_C), false);
});
