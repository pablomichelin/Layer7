import test from 'node:test';
import assert from 'node:assert/strict';
import {
  buildLicenseActionConfirmMessage,
  buildLicenseDeliveryPack,
} from './license-delivery-pack.js';

test('buildLicenseDeliveryPack includes key, activate command and summary fields', () => {
  const pack = buildLicenseDeliveryPack({
    license_key: 'abc123',
    customer_name: 'Systemup',
    features: 'base',
    expiry: '2027-07-29',
    hardware_id: 'deadbeef',
  });

  assert.match(pack, /Cliente: Systemup/);
  assert.match(pack, /SKU: Standard \(base\)/);
  assert.match(pack, /Expira: 29\/07\/2027/);
  assert.match(pack, /Equipamento: Vinculada/);
  assert.match(pack, /Chave: abc123/);
  assert.match(pack, /layer7d --activate abc123/);
});

test('buildLicenseActionConfirmMessage lists action context', () => {
  const message = buildLicenseActionConfirmMessage('Revogar esta licença.', {
    license_key: 'abc123',
    customer_name: 'Lasalle',
    features: 'base,identity,mitm',
    expiry: '2026-03-30',
  });

  assert.match(message, /^Revogar esta licença\./);
  assert.match(message, /Cliente: Lasalle/);
  assert.match(message, /Identity \+ MITM/);
  assert.match(message, /Por activar/);
  assert.match(message, /Confirma\?/);
});
