import { useEffect, useMemo, useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth';
import { get } from '../api';
import { useDebouncedValue } from '../use-debounced-value.js';
import { listRecentCustomers } from '../recent-customers.js';
import { hasPermission } from '../permissions.js';
import {
  ADMIN_CUSTOMERS_ROUTE,
  ADMIN_DASHBOARD_ROUTE,
  ADMIN_LICENSES_ROUTE,
  ADMIN_AUDIT_ROUTE,
  ADMIN_SECURITY_ROUTE,
  ADMIN_USERS_ROUTE,
  ADMIN_INSTALLATIONS_ROUTE,
  ADMIN_LOGIN_ROUTE,
  buildAdminCustomerDetailRoute,
  buildAdminLicenseDetailRoute,
} from '../panel-routes.js';
import { PORTAL_VERSION } from '../portal-version.js';

const ALL_LINKS = [
  { to: ADMIN_DASHBOARD_ROUTE, label: 'Dashboard', icon: '📊', permission: 'dashboard.read' },
  { to: ADMIN_LICENSES_ROUTE, label: 'Licenças', icon: '🔑', permission: 'licenses.read' },
  { to: ADMIN_INSTALLATIONS_ROUTE, label: 'Instalações', icon: '🖥️', permission: 'licenses.read' },
  { to: ADMIN_CUSTOMERS_ROUTE, label: 'Clientes', icon: '👥', permission: 'customers.read' },
  { to: ADMIN_AUDIT_ROUTE, label: 'Auditoria', icon: '📋', permission: 'audit.read' },
  { to: ADMIN_USERS_ROUTE, label: 'Utilizadores', icon: '🧑‍💻', permission: 'users.manage' },
  { to: ADMIN_SECURITY_ROUTE, label: 'Segurança', icon: '🔒', permission: 'security.self' },
];

export default function Sidebar() {
  const navigate = useNavigate();
  const { admin, logout } = useAuth();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [results, setResults] = useState(null);
  const [recent, setRecent] = useState(() => listRecentCustomers());
  const canSearch = hasPermission(admin, 'search.read');
  const links = useMemo(
    () => ALL_LINKS.filter((link) => hasPermission(admin, link.permission)),
    [admin]
  );

  useEffect(() => {
    setRecent(listRecentCustomers());
  }, []);

  useEffect(() => {
    if (!canSearch || !debouncedSearch || debouncedSearch.trim().length < 2) {
      setResults(null);
      return undefined;
    }

    const controller = new AbortController();
    get(`/search?q=${encodeURIComponent(debouncedSearch.trim())}`, { signal: controller.signal })
      .then(setResults)
      .catch((err) => {
        if (err?.name !== 'AbortError') console.error(err);
      });

    return () => controller.abort();
  }, [debouncedSearch, canSearch]);

  async function handleLogout() {
    await logout();
    navigate(ADMIN_LOGIN_ROUTE, { replace: true });
  }

  return (
    <aside className="w-64 bg-brand-700 text-white flex flex-col min-h-screen">
      <div className="p-6 border-b border-brand-600">
        <h1 className="text-lg font-bold tracking-tight">Layer7 License Manager</h1>
        <p className="text-brand-200 text-xs mt-1">por Systemup</p>
        <p className="text-brand-300 text-xs mt-1">v{PORTAL_VERSION}</p>
        {admin && <p className="text-brand-100 text-xs mt-3">{admin.email}</p>}
      </div>

      {canSearch && (
        <div className="px-4 py-3 border-b border-brand-600">
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Busca global..."
            className="w-full px-3 py-2 rounded-lg text-sm text-gray-900 outline-none"
          />
          {results && (
            <div className="mt-2 max-h-56 overflow-auto text-xs space-y-2">
              {(results.customers || []).map((customer) => (
                <button
                  key={`c-${customer.id}`}
                  type="button"
                  className="block w-full text-left text-brand-50 hover:underline"
                  onClick={() => {
                    navigate(buildAdminCustomerDetailRoute(customer.id));
                    setSearch('');
                    setResults(null);
                  }}
                >
                  Cliente: {customer.name}
                </button>
              ))}
              {(results.licenses || []).map((license) => (
                <button
                  key={`l-${license.id}`}
                  type="button"
                  className="block w-full text-left text-brand-50 hover:underline"
                  onClick={() => {
                    navigate(buildAdminLicenseDetailRoute(license.id));
                    setSearch('');
                    setResults(null);
                  }}
                >
                  Licença: {license.license_key?.slice(0, 12)}… ({license.customer_name || '—'})
                </button>
              ))}
              {!results.customers?.length && !results.licenses?.length && (
                <p className="text-brand-200">Sem resultados</p>
              )}
            </div>
          )}
        </div>
      )}

      <nav className="flex-1 py-4">
        {links.map(({ to, label, icon }) => (
          <NavLink
            key={to}
            to={to}
            className={({ isActive }) =>
              `flex items-center gap-3 px-6 py-3 text-sm transition-colors ${
                isActive
                  ? 'bg-brand-600 text-white font-medium'
                  : 'text-brand-100 hover:bg-brand-600/50'
              }`
            }
          >
            <span>{icon}</span>
            {label}
          </NavLink>
        ))}

        {recent.length > 0 && hasPermission(admin, 'customers.read') && (
          <div className="px-6 pt-4">
            <p className="text-xs uppercase tracking-wide text-brand-300 mb-2">Recentes</p>
            <ul className="space-y-1">
              {recent.map((customer) => (
                <li key={customer.id}>
                  <button
                    type="button"
                    onClick={() => navigate(buildAdminCustomerDetailRoute(customer.id))}
                    className="text-sm text-brand-100 hover:underline text-left"
                  >
                    {customer.name}
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )}
      </nav>

      <button
        onClick={handleLogout}
        className="m-4 px-4 py-2 text-sm bg-brand-800 hover:bg-brand-900 rounded transition-colors"
      >
        Sair
      </button>
    </aside>
  );
}
