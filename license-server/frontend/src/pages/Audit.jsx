import { useState, useEffect } from 'react';
import { get } from '../api';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';

export default function Audit() {
  const [events, setEvents] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [eventType, setEventType] = useState('');
  const [resultFilter, setResultFilter] = useState('');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  function load() {
    setLoading(true);
    const params = new URLSearchParams({ page, limit: 30 });
    if (eventType) params.set('event_type', eventType);
    if (resultFilter) params.set('result', resultFilter);
    if (search) params.set('search', search);
    get(`/audit?${params}`)
      .then((d) => {
        setEvents(d.events || []);
        setTotal(d.total || 0);
        setPages(d.pages || 1);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, [page, eventType, resultFilter, search]);

  const columns = [
    {
      key: 'created_at',
      label: 'Data',
      render: (r) => new Date(r.created_at).toLocaleString('pt-BR'),
    },
    { key: 'event_type', label: 'Evento', render: (r) => <code className="text-xs">{r.event_type}</code> },
    { key: 'component', label: 'Componente' },
    {
      key: 'result',
      label: 'Resultado',
      render: (r) => <StatusBadge status={r.result === 'success' ? 'success' : r.result === 'denied' ? 'fail' : r.result} />,
    },
    { key: 'actor_identifier', label: 'Actor', render: (r) => r.actor_identifier || '—' },
    { key: 'ip_address', label: 'IP', render: (r) => r.ip_address || '—' },
    { key: 'reason', label: 'Motivo', render: (r) => r.reason || '—' },
  ];

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-800 mb-6">Auditoria ({total})</h2>

      <div className="mb-4 flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Buscar actor, rota, IP, motivo..."
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm w-72 focus:ring-2 focus:ring-brand-500 outline-none"
        />
        <input
          type="text"
          placeholder="event_type (ex: license_renewed)"
          value={eventType}
          onChange={(e) => { setEventType(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm w-64 focus:ring-2 focus:ring-brand-500 outline-none"
        />
        <select
          value={resultFilter}
          onChange={(e) => { setResultFilter(e.target.value); setPage(1); }}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none"
        >
          <option value="">Todos os resultados</option>
          <option value="success">success</option>
          <option value="denied">denied</option>
          <option value="error">error</option>
        </select>
      </div>

      {loading ? <p className="text-gray-500">Carregando...</p> : (
        <>
          <DataTable columns={columns} rows={events} emptyMessage="Nenhum evento de auditoria" />
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
