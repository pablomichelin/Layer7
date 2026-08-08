import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { get, del } from '../api';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';
import CopyButton from '../components/CopyButton';
import { formatSkuLabel, isLicenseBound } from '../license-display.js';
import { formatCalendarDate, formatDateTime } from '../format-date.js';
import { summarizeCustomerLicenses } from '../customer-license-summary.js';
import {
  ADMIN_CUSTOMERS_ROUTE,
  buildAdminCustomerEditRoute,
  buildAdminLicenseDetailRoute,
  buildAdminLicenseNewRoute,
} from '../panel-routes.js';

export default function CustomerDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    get(`/customers/${id}`, { signal: controller.signal })
      .then(setData)
      .catch((err) => {
        if (err?.name !== 'AbortError') console.error(err);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [id]);

  async function handleArchive() {
    if (!confirm('Arquivar este cliente e as licenças não activas associadas?')) return;
    try {
      await del(`/customers/${id}`);
      navigate(ADMIN_CUSTOMERS_ROUTE);
    } catch (err) {
      alert(err.message);
    }
  }

  if (loading) return <p className="text-gray-500">Carregando...</p>;
  if (!data) return <p className="text-red-500">Cliente não encontrado</p>;

  const { customer, licenses } = data;
  const summary = summarizeCustomerLicenses(licenses);

  const columns = [
    {
      key: 'license_key',
      label: 'Chave',
      render: (r) => (
        <div className="flex items-start gap-2 max-w-md" onClick={(e) => e.stopPropagation()}>
          <button
            type="button"
            onClick={() => navigate(buildAdminLicenseDetailRoute(r.id, { fromCustomerId: id }))}
            className="text-left"
          >
            <code className="text-xs text-brand-700 hover:underline break-all">{r.license_key}</code>
          </button>
          <CopyButton text={r.license_key} />
        </div>
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
      key: 'activated_at',
      label: 'Activada',
      render: (r) => (r.activated_at ? formatDateTime(r.activated_at).split(',')[0] : 'Nunca'),
    },
    {
      key: 'actions',
      label: '',
      render: (r) => (
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            navigate(buildAdminLicenseDetailRoute(r.id, { fromCustomerId: id }));
          }}
          className="text-xs text-brand-600 hover:underline"
        >
          Ver
        </button>
      ),
    },
  ];

  return (
    <div>
      <button onClick={() => navigate(ADMIN_CUSTOMERS_ROUTE)} className="text-sm text-brand-600 hover:underline mb-4 block">&larr; Voltar</button>

      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
          <h2 className="text-xl font-bold text-gray-800">{customer.name}</h2>
          <button
            type="button"
            onClick={() => navigate(buildAdminLicenseNewRoute({ customerId: id }))}
            className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg transition-colors"
          >
            Nova licença
          </button>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
          <div className="rounded-lg bg-gray-50 border border-gray-100 px-3 py-2">
            <p className="text-xs text-gray-500">Total</p>
            <p className="text-lg font-semibold text-gray-800">{summary.total}</p>
          </div>
          <div className="rounded-lg bg-green-50 border border-green-100 px-3 py-2">
            <p className="text-xs text-green-700">Activas</p>
            <p className="text-lg font-semibold text-green-800">{summary.active}</p>
          </div>
          <div className="rounded-lg bg-amber-50 border border-amber-100 px-3 py-2">
            <p className="text-xs text-amber-700">A expirar 30d</p>
            <p className="text-lg font-semibold text-amber-800">{summary.expiring30d}</p>
          </div>
          <div className="rounded-lg bg-red-50 border border-red-100 px-3 py-2">
            <p className="text-xs text-red-700">Revogadas</p>
            <p className="text-lg font-semibold text-red-800">{summary.revoked}</p>
          </div>
          <div className="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2">
            <p className="text-xs text-blue-700">Bound</p>
            <p className="text-lg font-semibold text-blue-800">{summary.bound}</p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><span className="text-gray-500">Email:</span> <span className="ml-2">{customer.email || '—'}</span></div>
          <div><span className="text-gray-500">Telefone:</span> <span className="ml-2">{customer.phone || '—'}</span></div>
          <div><span className="text-gray-500">CNPJ:</span> <span className="ml-2">{customer.cnpj || '—'}</span></div>
          <div><span className="text-gray-500">Tags:</span> <span className="ml-2">{customer.tags || '—'}</span></div>
          <div><span className="text-gray-500">Criado em:</span> <span className="ml-2">{formatDateTime(customer.created_at)}</span></div>
          {customer.notes && <div className="md:col-span-2"><span className="text-gray-500">Notas:</span> <span className="ml-2">{customer.notes}</span></div>}
        </div>
        <div className="flex gap-3 mt-6">
          <button onClick={() => navigate(buildAdminCustomerEditRoute(id))} className="px-4 py-2 border border-brand-600 text-brand-700 hover:bg-brand-50 text-sm rounded-lg transition-colors">
            Editar cliente
          </button>
          <button onClick={handleArchive} className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
            Arquivar Cliente
          </button>
        </div>
      </div>

      <h3 className="text-lg font-semibold text-gray-700 mb-3">Licenças ({summary.total})</h3>
      <DataTable
        columns={columns}
        rows={licenses}
        emptyMessage="Nenhuma licença — use Nova licença para emitir a primeira chave."
        onRowClick={(row) => navigate(buildAdminLicenseDetailRoute(row.id, { fromCustomerId: id }))}
      />
    </div>
  );
}
