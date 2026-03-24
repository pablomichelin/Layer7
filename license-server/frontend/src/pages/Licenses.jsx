import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { get, post, del } from '../api';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';

export default function Licenses() {
  const [licenses, setLicenses] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  function load() {
    setLoading(true);
    const params = new URLSearchParams({ page, limit: 20 });
    if (statusFilter) params.set('status', statusFilter);
    if (search) params.set('search', search);
    get(`/licenses?${params}`)
      .then((d) => { setLicenses(d.licenses); setTotal(d.total); setPages(d.pages); })
      .catch(console.error)
      .finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, [page, statusFilter, search]);

  async function handleRevoke(id, e) {
    e.stopPropagation();
    if (!confirm('Tem certeza que deseja revogar esta licença?')) return;
    try {
      await post(`/licenses/${id}/revoke`, {});
      load();
    } catch (err) {
      alert(err.message);
    }
  }

  async function handleDelete(id, e) {
    e.stopPropagation();
    if (!confirm('Apagar esta licença permanentemente?')) return;
    try {
      await del(`/licenses/${id}`);
      load();
    } catch (err) {
      alert(err.message);
    }
  }

  const columns = [
    { key: 'license_key', label: 'Chave', render: (r) => <code className="text-xs">{r.license_key.slice(0, 16)}...</code> },
    { key: 'customer_name', label: 'Cliente', render: (r) => r.customer_name || '—' },
    { key: 'expiry', label: 'Expira', render: (r) => new Date(r.expiry).toLocaleDateString('pt-BR') },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'created_at', label: 'Criada', render: (r) => new Date(r.created_at).toLocaleDateString('pt-BR') },
    {
      key: 'actions', label: '', render: (r) => (
        <div className="flex gap-2">
          <button onClick={() => navigate(`/licenses/${r.id}`)} className="text-xs text-brand-600 hover:underline">Ver</button>
          {r.status === 'active' && (
            <button onClick={(e) => handleRevoke(r.id, e)} className="text-xs text-red-600 hover:underline">Revogar</button>
          )}
          {r.status !== 'active' && (
            <button onClick={(e) => handleDelete(r.id, e)} className="text-xs text-red-600 hover:underline">Apagar</button>
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
          onClick={() => navigate('/licenses/new')}
          className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors"
        >
          Nova Licença
        </button>
      </div>

      <div className="mb-4 flex gap-3">
        <input
          type="text"
          placeholder="Buscar por chave ou cliente..."
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
      </div>

      {loading ? <p className="text-gray-500">Carregando...</p> : (
        <>
          <DataTable columns={columns} rows={licenses} emptyMessage="Nenhuma licença encontrada" />
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
