export const ADMIN_LOGIN_ROUTE = '/login';
export const ADMIN_DASHBOARD_ROUTE = '/dashboard';
export const ADMIN_LICENSES_ROUTE = '/licenses';
export const ADMIN_CUSTOMERS_ROUTE = '/customers';
export const ADMIN_AUDIT_ROUTE = '/audit';
export const ADMIN_LICENSES_NEW_ROUTE = `${ADMIN_LICENSES_ROUTE}/new`;
export const ADMIN_CUSTOMERS_NEW_ROUTE = `${ADMIN_CUSTOMERS_ROUTE}/new`;

export function buildAdminLicenseDetailRoute(id) {
  return `${ADMIN_LICENSES_ROUTE}/${id}`;
}

export function buildAdminLicenseEditRoute(id) {
  return `${buildAdminLicenseDetailRoute(id)}/edit`;
}

export function buildAdminLicenseNewRoute({ customerId } = {}) {
  if (customerId === undefined || customerId === null || customerId === '') {
    return ADMIN_LICENSES_NEW_ROUTE;
  }
  const params = new URLSearchParams({ customer_id: String(customerId) });
  return `${ADMIN_LICENSES_NEW_ROUTE}?${params.toString()}`;
}

export function buildAdminCustomerDetailRoute(id) {
  return `${ADMIN_CUSTOMERS_ROUTE}/${id}`;
}

export function buildAdminCustomerEditRoute(id) {
  return `${buildAdminCustomerDetailRoute(id)}/edit`;
}
