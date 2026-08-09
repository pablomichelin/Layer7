import test from 'node:test';
import assert from 'node:assert/strict';
import { hasPermission, groupPermissionCatalog } from './permissions.js';

test('hasPermission respects owner and technician lists', () => {
  assert.equal(hasPermission({ is_owner: true }, 'users.manage'), true);
  assert.equal(hasPermission({ is_owner: false, permissions: ['licenses.read'] }, 'licenses.create'), false);
  assert.equal(hasPermission({ is_owner: false, permissions: ['licenses.create'] }, 'licenses.create'), true);
});

test('groupPermissionCatalog groups by group label', () => {
  const groups = groupPermissionCatalog([
    { key: 'a', group: 'Licenças', label: 'A' },
    { key: 'b', group: 'Clientes', label: 'B' },
    { key: 'c', group: 'Licenças', label: 'C' },
  ]);
  assert.equal(groups.Licenças.length, 2);
  assert.equal(groups.Clientes.length, 1);
});
