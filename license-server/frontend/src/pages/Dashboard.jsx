import { useState, useEffect } from 'react';
import { get } from '../api';
import StatsCard from '../components/StatsCard';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    get('/dashboard').then(setData).catch(console.error).finally(() => setLoading(false));
  }, []);

  if (loading) return <p className="text-gray-500">Carregando...</p>;
  if (!data) return <p className="text-red-500">Erro ao carregar dashboard</p>;

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

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <StatsCard label="Licenças Activas" value={data.licenses.active} color="green" />
        <StatsCard label="Expiradas" value={data.licenses.expired} color="yellow" />
        <StatsCard label="Revogadas" value={data.licenses.revoked} color="red" />
        <StatsCard label="Total Clientes" value={data.customers} color="blue" />
      </div>

      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-lg font-semibold text-gray-700">Últimas activações</h3>
        <span className="text-sm text-gray-500">{data.activations_24h} nas últimas 24h</span>
      </div>

      <DataTable columns={columns} rows={data.recent_activations} emptyMessage="Nenhuma activação registada" />
    </div>
  );
}
