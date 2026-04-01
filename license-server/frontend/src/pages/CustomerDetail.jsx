import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { get, del } from '../api';
import DataTable from '../components/DataTable';
import StatusBadge from '../components/StatusBadge';

export default function CustomerDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    get(`/customers/${id}`).then(setData).catch(console.error).finally(() => setLoading(false));
  }, [id]);

  async function handleArchive() {
    if (!confirm('Arquivar este cliente e as licenças não activas associadas?')) return;
    try {
      await del(`/customers/${id}`);
      navigate('/customers');
    } catch (err) {
      alert(err.message);
    }
  }

  if (loading) return <p className="text-gray-500">Carregando...</p>;
  if (!data) return <p className="text-red-500">Cliente não encontrado</p>;

  const { customer, licenses } = data;

  const columns = [
    { key: 'license_key', label: 'Chave', render: (r) => <code className="text-xs">{r.license_key.slice(0, 16)}...</code> },
    { key: 'expiry', label: 'Expira', render: (r) => new Date(r.expiry).toLocaleDateString('pt-BR') },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'activated_at', label: 'Activada', render: (r) => r.activated_at ? new Date(r.activated_at).toLocaleDateString('pt-BR') : 'Nunca' },
    { key: 'actions', label: '', render: (r) => (
      <button onClick={() => navigate(`/licenses/${r.id}`)} className="text-xs text-brand-600 hover:underline">Ver</button>
    )},
  ];

  return (
    <div>
      <button onClick={() => navigate('/customers')} className="text-sm text-brand-600 hover:underline mb-4 block">&larr; Voltar</button>

      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <h2 className="text-xl font-bold text-gray-800 mb-4">{customer.name}</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><span className="text-gray-500">Email:</span> <span className="ml-2">{customer.email || '—'}</span></div>
          <div><span className="text-gray-500">Telefone:</span> <span className="ml-2">{customer.phone || '—'}</span></div>
          <div><span className="text-gray-500">Criado em:</span> <span className="ml-2">{new Date(customer.created_at).toLocaleDateString('pt-BR')}</span></div>
          {customer.notes && <div className="md:col-span-2"><span className="text-gray-500">Notas:</span> <span className="ml-2">{customer.notes}</span></div>}
        </div>
        <div className="flex gap-3 mt-6">
          <button onClick={() => navigate(`/customers/${id}/edit`)} className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg transition-colors">
            Editar
          </button>
          <button onClick={handleArchive} className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
            Arquivar Cliente
          </button>
        </div>
      </div>

      <h3 className="text-lg font-semibold text-gray-700 mb-3">Licenças ({licenses.length})</h3>
      <DataTable columns={columns} rows={licenses} emptyMessage="Nenhuma licença" />
    </div>
  );
}
