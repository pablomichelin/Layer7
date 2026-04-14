import test from 'node:test';
import assert from 'node:assert/strict';
import {
  ADMIN_CUSTOMERS_NEW_ROUTE,
  ADMIN_CUSTOMERS_ROUTE,
  ADMIN_DASHBOARD_ROUTE,
  ADMIN_LICENSES_NEW_ROUTE,
  ADMIN_LICENSES_ROUTE,
  ADMIN_LOGIN_ROUTE,
  buildAdminCustomerDetailRoute,
  buildAdminCustomerEditRoute,
  buildAdminLicenseDetailRoute,
  buildAdminLicenseEditRoute,
} from './panel-routes.js';

test('panel routes expose the canonical navigation destinations', () => {
  assert.equal(ADMIN_LOGIN_ROUTE, '/login');
  assert.equal(ADMIN_DASHBOARD_ROUTE, '/dashboard');
  assert.equal(ADMIN_LICENSES_ROUTE, '/licenses');
  assert.equal(ADMIN_CUSTOMERS_ROUTE, '/customers');
  assert.equal(ADMIN_LICENSES_NEW_ROUTE, '/licenses/new');
  assert.equal(ADMIN_CUSTOMERS_NEW_ROUTE, '/customers/new');
});

test('panel routes build canonical detail and edit destinations', () => {
  assert.equal(buildAdminLicenseDetailRoute(42), '/licenses/42');
  assert.equal(buildAdminLicenseEditRoute(42), '/licenses/42/edit');
  assert.equal(buildAdminCustomerDetailRoute(7), '/customers/7');
  assert.equal(buildAdminCustomerEditRoute(7), '/customers/7/edit');
});
