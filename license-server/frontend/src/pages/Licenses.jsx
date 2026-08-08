import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { get, post, del, api } from '../api';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';
import CopyButton from '../components/CopyButton';
import CustomerSelect from '../components/CustomerSelect.jsx';
import {
  formatLicenseEquipmentLabel,
  formatSkuLabel,
  isLicenseBound,
  LICENSE_EQUIPMENT_COLUMN_LABEL,
} from '../license-display.js';
import { formatCalendarDate, formatDateTime } from '../format-date.js';
import { useDebouncedValue } from '../use-debounced-value.js';
import { buildLicenseActionConfirmMessage } from '../license-delivery-pack.js';
import {
  ADMIN_LICENSES_NEW_ROUTE,
  buildAdminCustomerDetailRoute,
  buildAdminLicenseEditRoute,
  buildAdminLicenseDetailRoute,
} from '../panel-routes.js';

function truncateNotes(value, max = 40) {
  if (!value) return '—';
  const text = String(value);
  return text.length > max ? `${text.slice(0, max)}…` : text;
}

export default function Licenses() {
  const [licenses, setLicenses] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [customerFilter, setCustomerFilter] = useState('');
  const [boundFilter, setBoundFilter] = useState('');
  const [expiringFilter, setExpiringFilter] = useState('');
  const [staleFilter, setStaleFilter] = useState('');
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
    const stale = searchParams.get('stale_checkin_days') || '';
    if (status) setStatusFilter(status);
    if (expiring) setExpiringFilter(expiring);
    if (customerId) setCustomerFilter(customerId);
    if (bound) setBoundFilter(bound);
    if (stale) setStaleFilter(stale);
  }, [searchParams]);

  function buildListParams({ forCsv = false } = {}) {
    const params = new URLSearchParams();
    if (!forCsv) {
      params.set('page', String(page));
      params.set('limit', '20');
    } else {
      params.set('format', 'csv');
    }
    if (statusFilter) params.set('status', statusFilter);
    if (customerFilter) params.set('customer_id', customerFilter);
    if (boundFilter) params.set('bound', boundFilter);
    if (expiringFilter) params.set('expiring_within_days', expiringFilter);
    if (staleFilter) params.set('stale_checkin_days', staleFilter);
    if (debouncedSearch) params.set('search', debouncedSearch);
    return params;
  }

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    const params = buildListParams();

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
  }, [page, statusFilter, customerFilter, boundFilter, expiringFilter, staleFilter, debouncedSearch, listEpoch]);

  async function handleRevokeReload(row, e) {
    e.stopPropagation();
    if (!confirm(buildLicenseActionConfirmMessage('Revogar esta licença.', row))) return;
    try {
      await post(`/licenses/${row.id}/revoke`, {});
      setListEpoch((current) => current + 1);
    } catch (err) {
      alert(err.message);
    }
  }

  async function handleArchive(row, e) {
    e.stopPropagation();
    if (!confirm(buildLicenseActionConfirmMessage('Arquivar esta licença.', row))) return;
    try {
      await del(`/licenses/${row.id}`);
      setListEpoch((current) => current + 1);
    } catch (err) {
      alert(err.message);
    }
  }

  async function handleExportCsv() {
    try {
      const params = buildListParams({ forCsv: true });
      const res = await api(`/licenses?${params}`, { method: 'GET', raw: true });
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'licenses.csv';
      a.click();
      URL.revokeObjectURL(url);
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
      label: LICENSE_EQUIPMENT_COLUMN_LABEL,
      render: (r) => (
        <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-medium ${
          isLicenseBound(r) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700'
        }`}>
          {formatLicenseEquipmentLabel(r)}
        </span>
      ),
    },
    { key: 'expiry', label: 'Expira', render: (r) => formatCalendarDate(r.expiry) },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    {
      key: 'last_check_in_at',
      label: 'Último check-in',
      render: (r) => formatDateTime(r.last_check_in_at),
    },
    {
      key: 'notes',
      label: 'Notas',
      render: (r) => (
        <span title={r.notes || ''} className="text-xs text-gray-600">
          {truncateNotes(r.notes)}
        </span>
      ),
    },
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
            <button type="button" onClick={(e) => handleRevokeReload(r, e)} className="text-xs text-red-600 hover:underline">Revogar</button>
          )}
          {r.status !== 'active' && (
            <button type="button" onClick={(e) => handleArchive(r, e)} className="text-xs text-red-600 hover:underline">Arquivar</button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Licenças ({total})</h2>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={handleExportCsv}
            className="px-4 py-2 border border-gray-300 hover:bg-gray-50 text-sm font-medium rounded-lg transition-colors"
          >
            Exportar CSV
          </button>
          <button
            onClick={() => navigate(ADMIN_LICENSES_NEW_ROUTE)}
            className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors"
          >
            Nova Licença
          </button>
        </div>
      </div>

      <div className="mb-4 flex flex-wrap gap-3 items-start">
        <input
          type="text"
          placeholder="Buscar chave, cliente, hardware ou notas..."
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
          <option value="">Equipamento (todos)</option>
          <option value="yes">Só vinculadas</option>
          <option value="no">Só por activar</option>
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
        <select
          value={staleFilter}
          onChange={(e) => { setStaleFilter(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none"
        >
          <option value="">Check-in</option>
          <option value="7">Sem check-in &gt; 7 dias</option>
          <option value="14">Sem check-in &gt; 14 dias</option>
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
