import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { get } from '../api';
import StatsCard from '../components/StatsCard';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';
import { formatCalendarDate } from '../format-date.js';
import {
  ADMIN_LICENSES_ROUTE,
  buildAdminLicenseDetailRoute,
} from '../panel-routes.js';

function ActionQueueSection({ title, count, href, rows, emptyLabel, renderMeta }) {
  return (
    <div className="bg-white rounded-lg shadow p-4">
      <div className="flex items-center justify-between mb-3">
        <div>
          <h4 className="font-semibold text-gray-800">{title}</h4>
          <p className="text-xs text-gray-500">{count} no total</p>
        </div>
        {href ? (
          <Link to={href} className="text-sm text-brand-600 hover:underline">Ver lista</Link>
        ) : (
          <span className="text-xs text-gray-400">Só alerta</span>
        )}
      </div>
      {rows.length === 0 ? (
        <p className="text-sm text-gray-400">{emptyLabel}</p>
      ) : (
        <ul className="space-y-2">
          {rows.map((row) => (
            <li key={row.id} className="text-sm flex justify-between gap-2">
              <Link to={buildAdminLicenseDetailRoute(row.id)} className="text-brand-700 hover:underline truncate">
                {row.customer_name || '—'} · <code className="text-xs">{row.license_key?.slice(0, 10)}…</code>
              </Link>
              <span className="text-xs text-gray-500 shrink-0">
                {renderMeta
                  ? renderMeta(row)
                  : (row.expiry
                    ? formatCalendarDate(row.expiry)
                    : (row.last_check_in_at
                      ? new Date(row.last_check_in_at).toLocaleDateString('pt-BR')
                      : '—'))}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    get('/dashboard').then(setData).catch(console.error).finally(() => setLoading(false));
  }, []);

  if (loading) return <p className="text-gray-500">Carregando...</p>;
  if (!data) return <p className="text-red-500">Erro ao carregar dashboard</p>;

  const queue = data.action_queue || {};
  const licenses = data.licenses || {};

  const columns = [
    { key: 'created_at', label: 'Data', render: (r) => new Date(r.created_at).toLocaleString('pt-BR') },
    { key: 'customer_name', label: 'Cliente', render: (r) => r.customer_name || '—' },
    { key: 'result', label: 'Resultado', render: (r) => <StatusBadge status={r.result} /> },
    { key: 'ip_address', label: 'IP', render: (r) => r.ip_address || '—' },
    { key: 'license_key', label: 'Chave', render: (r) => r.license_key ? r.license_key.slice(0, 12) + '...' : '—' },
  ];

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-800 mb-6">Dashboard</h2>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
        <Link to={ADMIN_LICENSES_ROUTE} className="block">
          <StatsCard label="Licenças Activas" value={licenses.active} color="green" />
        </Link>
        <Link to={`${ADMIN_LICENSES_ROUTE}?expiring_within_days=30`} className="block">
          <StatsCard label="A expirar (30d)" value={licenses.expiring_30d ?? 0} color="yellow" />
        </Link>
        <Link to={`${ADMIN_LICENSES_ROUTE}?status=expired`} className="block">
          <StatsCard label="Expiradas efectivas" value={licenses.expired} color="yellow" />
        </Link>
        <Link to={`${ADMIN_LICENSES_ROUTE}?status=revoked`} className="block">
          <StatsCard label="Revogadas" value={licenses.revoked} color="red" />
        </Link>
        <StatsCard label="Total Clientes" value={data.customers} color="blue" />
        <StatsCard label="Total Licenças" value={licenses.total} color="blue" />
      </div>

      <h3 className="text-lg font-semibold text-gray-700 mb-3">Precisa de acção</h3>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <ActionQueueSection
          title="A expirar em 30 dias"
          count={licenses.expiring_30d ?? 0}
          href={`${ADMIN_LICENSES_ROUTE}?expiring_within_days=30`}
          rows={queue.expiring_30d || []}
          emptyLabel="Nenhuma licença a expirar em breve"
        />
        <ActionQueueSection
          title="Expiradas ainda vinculadas"
          count={licenses.expired_bound ?? 0}
          href={`${ADMIN_LICENSES_ROUTE}?status=expired&bound=yes`}
          rows={queue.expired_bound || []}
          emptyLabel="Nenhuma expirada vinculada"
        />
        <ActionQueueSection
          title="Por activar há ≥ 7 dias"
          count={licenses.unbound_stale_7d ?? 0}
          href={`${ADMIN_LICENSES_ROUTE}?bound=no`}
          rows={queue.unbound_stale_7d || []}
          emptyLabel="Nenhuma pendente antiga"
        />
        <ActionQueueSection
          title="Sem check-in &gt; 7 dias"
          count={licenses.stale_checkin_7d ?? 0}
          href={`${ADMIN_LICENSES_ROUTE}?stale_checkin_days=7`}
          rows={queue.stale_checkin_7d || []}
          emptyLabel="Nenhuma vinculada em silêncio"
        />
        <ActionQueueSection
          title="Abuso multi-appliance (30d)"
          count={licenses.multi_appliance_abuse ?? 0}
          rows={queue.multi_appliance_abuse || []}
          emptyLabel="Nenhum sinal de abuso multi-appliance"
          renderMeta={(row) => (
            `${row.unexplained_count ?? row.distinct_count ?? '?'} hw não explicado`
          )}
        />
      </div>

      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-lg font-semibold text-gray-700">Últimas activações</h3>
        <span className="text-sm text-gray-500">{data.activations_24h} nas últimas 24h</span>
      </div>

      <DataTable columns={columns} rows={data.recent_activations} emptyMessage="Nenhuma activação registada" />
    </div>
  );
}
