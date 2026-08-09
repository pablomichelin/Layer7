const test = require('node:test');
const assert = require('node:assert/strict');
const {
  hasPermission,
  normalizePermissions,
  toPublicAdmin,
} = require('./admin-permissions');

test('normalizePermissions keeps known keys and drops users.manage for technicians', () => {
  assert.deepEqual(
    normalizePermissions(['licenses.read', 'users.manage', 'nope', 'licenses.read']),
    ['licenses.read']
  );
  assert.deepEqual(
    normalizePermissions(['licenses.read', 'users.manage'], { allowUsersManage: true }),
    ['licenses.read', 'users.manage']
  );
});

test('hasPermission grants owners everything', () => {
  assert.equal(hasPermission({ is_owner: true }, 'licenses.revoke'), true);
  assert.equal(hasPermission({ is_owner: false, permissions: ['licenses.read'] }, 'licenses.revoke'), false);
  assert.equal(hasPermission({ is_owner: false, permissions: ['licenses.revoke'] }, 'licenses.revoke'), true);
});

test('toPublicAdmin maps owner and technician', () => {
  assert.deepEqual(
    toPublicAdmin({
      id: 1,
      email: 'owner@example.com',
      name: 'Owner',
      is_owner: true,
      is_active: true,
      permissions: [],
    }).permissions,
    ['*']
  );

  assert.deepEqual(
    toPublicAdmin({
      id: 2,
      email: 'tech@example.com',
      name: 'Tech',
      is_owner: false,
      is_active: true,
      permissions: ['licenses.read', 'users.manage'],
    }).permissions,
    ['licenses.read']
  );
});
