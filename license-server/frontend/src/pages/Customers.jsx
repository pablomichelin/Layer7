import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { get, del } from '../api';
import DataTable from '../components/DataTable';
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
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  function load() {
    setLoading(true);
    const params = new URLSearchParams({ page, limit: 20 });
    if (search) params.set('search', search);
    get(`/customers?${params}`)
      .then((d) => { setCustomers(d.customers); setTotal(d.total); setPages(d.pages); })
      .catch(console.error)
      .finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, [page, search]);

  async function handleArchive(id, name, e) {
    e.stopPropagation();
    if (!confirm(`Arquivar cliente "${name}"?`)) return;
    try {
      await del(`/customers/${id}`);
      load();
    } catch (err) {
      alert(err.message);
    }
  }

  const columns = [
    { key: 'name', label: 'Nome' },
    { key: 'email', label: 'Email', render: (r) => r.email || '—' },
    { key: 'license_count', label: 'Licenças', render: (r) => r.license_count },
    { key: 'created_at', label: 'Criado em', render: (r) => new Date(r.created_at).toLocaleDateString('pt-BR') },
    {
      key: 'actions', label: '', render: (r) => (
        <div className="flex gap-2">
          <button onClick={() => navigate(buildAdminCustomerDetailRoute(r.id))} className="text-xs text-brand-600 hover:underline">Ver</button>
          <button onClick={() => navigate(buildAdminCustomerEditRoute(r.id))} className="text-xs text-brand-600 hover:underline">Editar</button>
          <button onClick={(e) => handleArchive(r.id, r.name, e)} className="text-xs text-red-600 hover:underline">Arquivar</button>
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
          placeholder="Buscar por nome ou email..."
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm w-72 focus:ring-2 focus:ring-brand-500 outline-none"
        />
      </div>

      {loading ? <p className="text-gray-500">Carregando...</p> : (
        <>
          <DataTable columns={columns} rows={customers} emptyMessage="Nenhum cliente encontrado" />
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
