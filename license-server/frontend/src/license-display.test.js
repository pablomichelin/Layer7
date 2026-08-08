import test from 'node:test';
import assert from 'node:assert/strict';
import {
  formatLicenseEquipmentLabel,
  formatSkuLabel,
  isLicenseBound,
  LICENSE_EQUIPMENT_COLUMN_LABEL,
} from './license-display.js';

test('formatSkuLabel maps known SKUs and falls back', () => {
  assert.equal(formatSkuLabel('base'), 'Standard (base)');
  assert.equal(formatSkuLabel('base,identity,mitm'), 'Identity + MITM');
  assert.equal(formatSkuLabel('custom'), 'custom');
  assert.equal(formatSkuLabel(''), '—');
});

test('equipment labels use plain Portuguese instead of Bound/Unbound', () => {
  assert.equal(LICENSE_EQUIPMENT_COLUMN_LABEL, 'Equipamento');
  assert.equal(formatLicenseEquipmentLabel({ hardware_id: 'abc' }), 'Vinculada');
  assert.equal(formatLicenseEquipmentLabel({ hardware_id: '  ' }), 'Por activar');
  assert.equal(formatLicenseEquipmentLabel(true), 'Vinculada');
  assert.equal(formatLicenseEquipmentLabel(false), 'Por activar');
  assert.equal(isLicenseBound({ hardware_id: 'x' }), true);
  assert.equal(isLicenseBound({}), false);
});
