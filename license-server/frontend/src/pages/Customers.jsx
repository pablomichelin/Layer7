import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { get, del } from '../api';
import DataTable from '../components/DataTable';
import { formatCalendarDate, formatDateTime } from '../format-date.js';
import { useDebouncedValue } from '../use-debounced-value.js';
import {
  ADMIN_CUSTOMERS_NEW_ROUTE,
  buildAdminCustomerDetailRoute,
  buildAdminCustomerEditRoute,
} from '../panel-routes.js';

export default function Customers() {
  const [customers, setCustomers] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [listEpoch, setListEpoch] = useState(0);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    const params = new URLSearchParams({ page, limit: 20 });
    if (debouncedSearch) params.set('search', debouncedSearch);

    get(`/customers?${params}`, { signal: controller.signal })
      .then((d) => {
        setCustomers(d.customers);
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
  }, [page, debouncedSearch, listEpoch]);

  async function handleArchive(id, name, e) {
    e.stopPropagation();
    if (!confirm(`Arquivar cliente "${name}" e as licenças não activas associadas?`)) return;
    try {
      await del(`/customers/${id}`);
      setPage(1);
      setListEpoch((current) => current + 1);
    } catch (err) {
      alert(err.message);
    }
  }

  const columns = [
    {
      key: 'name',
      label: 'Nome',
      render: (r) => (
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            navigate(buildAdminCustomerDetailRoute(r.id));
          }}
          className="text-left text-brand-700 hover:underline font-medium"
        >
          {r.name}
        </button>
      ),
    },
    { key: 'email', label: 'Email', render: (r) => r.email || '—' },
    { key: 'cnpj', label: 'CNPJ', render: (r) => r.cnpj || '—' },
    { key: 'tags', label: 'Tags', render: (r) => r.tags || '—' },
    {
      key: 'licenses',
      label: 'Licenças',
      render: (r) => {
        const totalLicenses = Number(r.license_count) || 0;
        const activeLicenses = Number(r.license_active_count) || 0;
        return (
          <span title="activas / total">
            <span className="font-medium text-green-800">{activeLicenses}</span>
            <span className="text-gray-400"> / </span>
            <span className="text-gray-700">{totalLicenses}</span>
          </span>
        );
      },
    },
    {
      key: 'created_at',
      label: 'Criado em',
      render: (r) => formatDateTime(r.created_at).split(',')[0] || formatDateTime(r.created_at),
    },
    {
      key: 'actions',
      label: '',
      render: (r) => (
        <div className="flex gap-2" onClick={(e) => e.stopPropagation()}>
          <button type="button" onClick={() => navigate(buildAdminCustomerDetailRoute(r.id))} className="text-xs text-brand-600 hover:underline">Ver</button>
          <button type="button" onClick={() => navigate(buildAdminCustomerEditRoute(r.id))} className="text-xs text-brand-600 hover:underline">Editar</button>
          <button type="button" onClick={(e) => handleArchive(r.id, r.name, e)} className="text-xs text-red-600 hover:underline">Arquivar</button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Clientes ({total})</h2>
        <button
          onClick={() => navigate(ADMIN_CUSTOMERS_NEW_ROUTE)}
          className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors"
        >
          Novo Cliente
        </button>
      </div>

      <div className="mb-4">
        <input
          type="text"
          placeholder="Buscar nome, email, CNPJ ou tags..."
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm w-80 focus:ring-2 focus:ring-brand-500 outline-none"
        />
      </div>

      {loading ? <p className="text-gray-500">Carregando...</p> : (
        <>
          <DataTable
            columns={columns}
            rows={customers}
            emptyMessage="Nenhum cliente encontrado"
            onRowClick={(row) => navigate(buildAdminCustomerDetailRoute(row.id))}
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
