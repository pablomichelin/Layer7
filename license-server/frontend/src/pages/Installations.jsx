import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { get } from '../api';
import DataTable from '../components/DataTable';
import { formatDateTime } from '../format-date.js';
import { useDebouncedValue } from '../use-debounced-value.js';
import {
  buildAdminInstallationDetailRoute,
} from '../panel-routes.js';

function staleLabel(lastSeenAt) {
  if (!lastSeenAt) return '—';
  const ageMs = Date.now() - new Date(lastSeenAt).getTime();
  return ageMs > 7 * 24 * 60 * 60 * 1000 ? 'stale' : 'activa';
}

export default function Installations() {
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [licensedFilter, setLicensedFilter] = useState('');
  const [staleFilter, setStaleFilter] = useState('');
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [loading, setLoading] = useState(true);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  useEffect(() => {
    const licensed = searchParams.get('licensed') || '';
    const stale = searchParams.get('stale_days') || '';
    if (licensed) setLicensedFilter(licensed);
    if (stale) setStaleFilter(stale);
  }, [searchParams]);

  useEffect(() => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('limit', '20');
    if (licensedFilter) params.set('licensed', licensedFilter);
    if (staleFilter) params.set('stale_days', staleFilter);
    if (debouncedSearch) params.set('search', debouncedSearch);
    setLoading(true);
    get(`/installations?${params.toString()}`)
      .then((payload) => {
        setRows(payload.installations || []);
        setTotal(payload.total || 0);
        setPages(payload.pages || 1);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [page, licensedFilter, staleFilter, debouncedSearch]);

  const columns = [
    {
      key: 'last_seen_at',
      label: 'Último contacto',
      render: (r) => (r.last_seen_at ? formatDateTime(r.last_seen_at) : '—'),
    },
    {
      key: 'fqdn',
      label: 'Nome',
      render: (r) => r.fqdn || r.hostname || '—',
    },
    { key: 'egress_ip', label: 'IP público', render: (r) => r.egress_ip || '—' },
    { key: 'wan_ipv4', label: 'WAN', render: (r) => r.wan_ipv4 || '—' },
    {
      key: 'versions',
      label: 'pfSense / Layer7',
      render: (r) => `${r.pfsense_version || '—'} / ${r.package_version || '—'}`,
    },
    { key: 'platform', label: 'Plataforma', render: (r) => r.platform || '—' },
    {
      key: 'license',
      label: 'Licença',
      render: (r) => (r.customer_name
        ? r.customer_name
        : (r.license_id ? 'com serial' : 'sem serial')),
    },
    {
      key: 'state',
      label: 'Estado',
      render: (r) => staleLabel(r.last_seen_at),
    },
  ];

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-800 mb-4">Instalações</h2>
      <p className="text-sm text-gray-500 mb-4">
        Caixas que enviaram o sinal de instalação/heartbeat, com ou sem serial. {total} no total.
      </p>

      <div className="flex flex-wrap gap-3 mb-4">
        <input
          type="search"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          placeholder="Hostname, domínio, IP, uniqueid…"
          className="border rounded px-3 py-2 text-sm w-64"
        />
        <select
          value={licensedFilter}
          onChange={(e) => { setLicensedFilter(e.target.value); setPage(1); }}
          className="border rounded px-3 py-2 text-sm"
        >
          <option value="">Todas</option>
          <option value="no">Sem serial</option>
          <option value="yes">Com licença</option>
        </select>
        <select
          value={staleFilter}
          onChange={(e) => { setStaleFilter(e.target.value); setPage(1); }}
          className="border rounded px-3 py-2 text-sm"
        >
          <option value="">Qualquer idade</option>
          <option value="7">Stale &gt; 7 dias</option>
        </select>
      </div>

      {loading ? (
        <p className="text-gray-500">Carregando...</p>
      ) : (
        <DataTable
          columns={columns}
          rows={rows}
          emptyMessage="Nenhuma instalação vista ainda."
          onRowClick={(row) => navigate(buildAdminInstallationDetailRoute(row.id))}
        />
      )}

      {pages > 1 && (
        <div className="flex gap-2 mt-4 text-sm">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((p) => p - 1)}
            className="px-3 py-1 border rounded disabled:opacity-40"
          >
            Anterior
          </button>
          <span className="py-1 text-gray-600">Página {page} / {pages}</span>
          <button
            type="button"
            disabled={page >= pages}
            onClick={() => setPage((p) => p + 1)}
            className="px-3 py-1 border rounded disabled:opacity-40"
          >
            Seguinte
          </button>
        </div>
      )}
    </div>
  );
}
