import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { get, post, del } from '../api';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';
import CopyButton from '../components/CopyButton';
import CustomerSelect from '../components/CustomerSelect.jsx';
import { formatSkuLabel, isLicenseBound } from '../license-display.js';
import { formatCalendarDate } from '../format-date.js';
import { useDebouncedValue } from '../use-debounced-value.js';
import {
  ADMIN_LICENSES_NEW_ROUTE,
  buildAdminCustomerDetailRoute,
  buildAdminLicenseEditRoute,
  buildAdminLicenseDetailRoute,
} from '../panel-routes.js';

export default function Licenses() {
  const [licenses, setLicenses] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [customerFilter, setCustomerFilter] = useState('');
  const [boundFilter, setBoundFilter] = useState('');
  const [expiringFilter, setExpiringFilter] = useState('');
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [listEpoch, setListEpoch] = useState(0);
  const [loading, setLoading] = useState(true);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  useEffect(() => {
    const status = searchParams.get('status') || '';
    const expiring = searchParams.get('expiring_within_days') || '';
    const customerId = searchParams.get('customer_id') || '';
    const bound = searchParams.get('bound') || '';
    if (status) setStatusFilter(status);
    if (expiring) setExpiringFilter(expiring);
    if (customerId) setCustomerFilter(customerId);
    if (bound) setBoundFilter(bound);
  }, [searchParams]);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    const params = new URLSearchParams({ page, limit: 20 });
    if (statusFilter) params.set('status', statusFilter);
    if (customerFilter) params.set('customer_id', customerFilter);
    if (boundFilter) params.set('bound', boundFilter);
    if (expiringFilter) params.set('expiring_within_days', expiringFilter);
    if (debouncedSearch) params.set('search', debouncedSearch);

    get(`/licenses?${params}`, { signal: controller.signal })
      .then((d) => {
        setLicenses(d.licenses);
        setTotal(d.total);
        setPages(d.pages);
      })
      .catch((err) => {
        if (err?.name !== 'AbortError') console.error(err);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [page, statusFilter, customerFilter, boundFilter, expiringFilter, debouncedSearch, listEpoch]);

  async function handleRevokeReload(id, e) {
    e.stopPropagation();
    if (!confirm('Tem certeza que deseja revogar esta licença?')) return;
    try {
      await post(`/licenses/${id}/revoke`, {});
      setListEpoch((current) => current + 1);
    } catch (err) {
      alert(err.message);
    }
  }

  async function handleArchive(id, e) {
    e.stopPropagation();
    if (!confirm('Arquivar esta licença?')) return;
    try {
      await del(`/licenses/${id}`);
      setListEpoch((current) => current + 1);
    } catch (err) {
      alert(err.message);
    }
  }

  const columns = [
    {
      key: 'license_key',
      label: 'Chave',
      render: (r) => (
        <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
          <code className="text-xs break-all">{r.license_key}</code>
          <CopyButton text={r.license_key} />
        </div>
      ),
    },
    {
      key: 'customer_name',
      label: 'Cliente',
      render: (r) => (
        r.customer_id ? (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              navigate(buildAdminCustomerDetailRoute(r.customer_id));
            }}
            className="text-brand-700 hover:underline font-medium"
          >
            {r.customer_name || `Cliente #${r.customer_id}`}
          </button>
        ) : (r.customer_name || '—')
      ),
    },
    { key: 'features', label: 'SKU', render: (r) => formatSkuLabel(r.features) },
    {
      key: 'bound',
      label: 'Bind',
      render: (r) => (
        <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-medium ${
          isLicenseBound(r) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700'
        }`}>
          {isLicenseBound(r) ? 'Bound' : 'Unbound'}
        </span>
      ),
    },
    { key: 'expiry', label: 'Expira', render: (r) => formatCalendarDate(r.expiry) },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    {
      key: 'created_at',
      label: 'Criada',
      render: (r) => (r.created_at ? new Date(r.created_at).toLocaleDateString('pt-BR') : '—'),
    },
    {
      key: 'actions',
      label: '',
      render: (r) => (
        <div className="flex gap-2" onClick={(e) => e.stopPropagation()}>
          <button type="button" onClick={() => navigate(buildAdminLicenseDetailRoute(r.id))} className="text-xs text-brand-600 hover:underline">Ver</button>
          {r.status !== 'revoked' && (
            <button type="button" onClick={() => navigate(buildAdminLicenseEditRoute(r.id))} className="text-xs text-brand-600 hover:underline">Editar</button>
          )}
          {r.status === 'active' && (
            <button type="button" onClick={(e) => handleRevokeReload(r.id, e)} className="text-xs text-red-600 hover:underline">Revogar</button>
          )}
          {r.status !== 'active' && (
            <button type="button" onClick={(e) => handleArchive(r.id, e)} className="text-xs text-red-600 hover:underline">Arquivar</button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Licenças ({total})</h2>
        <button
          onClick={() => navigate(ADMIN_LICENSES_NEW_ROUTE)}
          className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors"
        >
          Nova Licença
        </button>
      </div>

      <div className="mb-4 flex flex-wrap gap-3 items-start">
        <input
          type="text"
          placeholder="Buscar chave, cliente ou hardware..."
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm w-72 focus:ring-2 focus:ring-brand-500 outline-none"
        />
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none"
        >
          <option value="">Todos os status</option>
          <option value="active">Activas</option>
          <option value="expired">Expiradas</option>
          <option value="revoked">Revogadas</option>
        </select>
        <div className="min-w-[16rem]">
          <CustomerSelect
            value={customerFilter}
            onChange={(e) => { setCustomerFilter(e.target.value); setPage(1); }}
            emptyLabel="Todos os clientes"
          />
        </div>
        <select
          value={boundFilter}
          onChange={(e) => { setBoundFilter(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none"
        >
          <option value="">Bound / Unbound</option>
          <option value="yes">Só Bound</option>
          <option value="no">Só Unbound</option>
        </select>
        <select
          value={expiringFilter}
          onChange={(e) => { setExpiringFilter(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none"
        >
          <option value="">Expiração</option>
          <option value="30">A expirar em 30 dias</option>
          <option value="60">A expirar em 60 dias</option>
          <option value="90">A expirar em 90 dias</option>
        </select>
      </div>

      {loading ? <p className="text-gray-500">Carregando...</p> : (
        <>
          <DataTable
            columns={columns}
            rows={licenses}
            emptyMessage="Nenhuma licença encontrada"
            onRowClick={(row) => navigate(buildAdminLicenseDetailRoute(row.id))}
          />
          {pages > 1 && (
            <div className="flex items-center justify-center gap-2 mt-4">
              <button disabled={page <= 1} onClick={() => setPage(page - 1)} className="px-3 py-1 text-sm border rounded disabled:opacity-30">Anterior</button>
              <span className="text-sm text-gray-600">Página {page} de {pages}</span>
              <button disabled={page >= pages} onClick={() => setPage(page + 1)} className="px-3 py-1 text-sm border rounded disabled:opacity-30">Próxima</button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
