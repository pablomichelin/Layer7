import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { get, post, del, download } from '../api';
import StatusBadge from '../components/StatusBadge';
import DataTable from '../components/DataTable';

export default function LicenseDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  function load() {
    setLoading(true);
    get(`/licenses/${id}`).then(setData).catch(console.error).finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, [id]);

  async function handleRevoke() {
    if (!confirm('Tem certeza que deseja revogar esta licença?')) return;
    try {
      await post(`/licenses/${id}/revoke`, {});
      load();
    } catch (err) {
      alert(err.message);
    }
  }

  async function handleDownload() {
    try {
      const blob = await download(`/licenses/${id}/download`);
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `layer7-${data.license.license_key.slice(0, 8)}.lic`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      alert(err.message);
    }
  }

  if (loading) return <p className="text-gray-500">Carregando...</p>;
  if (!data) return <p className="text-red-500">Licença não encontrada</p>;

  const { license, activations } = data;

  const actColumns = [
    { key: 'created_at', label: 'Data', render: (r) => new Date(r.created_at).toLocaleString('pt-BR') },
    { key: 'result', label: 'Resultado', render: (r) => <StatusBadge status={r.result} /> },
    { key: 'ip_address', label: 'IP' },
    { key: 'hardware_id', label: 'Hardware ID', render: (r) => r.hardware_id ? <code className="text-xs">{r.hardware_id.slice(0, 16)}...</code> : '—' },
    { key: 'error_message', label: 'Erro', render: (r) => r.error_message || '—' },
  ];

  return (
    <div>
      <button onClick={() => navigate('/licenses')} className="text-sm text-brand-600 hover:underline mb-4 block">&larr; Voltar</button>

      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <div className="flex items-start justify-between mb-4">
          <h2 className="text-xl font-bold text-gray-800">Detalhes da Licença</h2>
          <StatusBadge status={license.status} />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><span className="text-gray-500">Chave:</span> <code className="ml-2">{license.license_key}</code></div>
          <div><span className="text-gray-500">Cliente:</span> <span className="ml-2">{license.customer_name || '—'}</span></div>
          <div><span className="text-gray-500">Expira:</span> <span className="ml-2">{new Date(license.expiry).toLocaleDateString('pt-BR')}</span></div>
          <div><span className="text-gray-500">Features:</span> <span className="ml-2">{license.features}</span></div>
          <div><span className="text-gray-500">Hardware ID:</span> <code className="ml-2 text-xs">{license.hardware_id || 'Não activada'}</code></div>
          <div><span className="text-gray-500">Activada em:</span> <span className="ml-2">{license.activated_at ? new Date(license.activated_at).toLocaleString('pt-BR') : 'Nunca'}</span></div>
          <div><span className="text-gray-500">Criada em:</span> <span className="ml-2">{new Date(license.created_at).toLocaleString('pt-BR')}</span></div>
          {license.revoked_at && <div><span className="text-gray-500">Revogada em:</span> <span className="ml-2">{new Date(license.revoked_at).toLocaleString('pt-BR')}</span></div>}
          {license.notes && <div className="md:col-span-2"><span className="text-gray-500">Notas:</span> <span className="ml-2">{license.notes}</span></div>}
        </div>

        <div className="flex gap-3 mt-6">
          {license.status === 'active' && (
            <button onClick={handleRevoke} className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
              Revogar
            </button>
          )}
          {license.status !== 'active' && (
            <button onClick={async () => {
              if (!confirm('Apagar esta licença permanentemente?')) return;
              try { await del(`/licenses/${id}`); navigate('/licenses'); } catch (err) { alert(err.message); }
            }} className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
              Apagar Licença
            </button>
          )}
          {license.hardware_id && (
            <button onClick={handleDownload} className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg transition-colors">
              Download .lic
            </button>
          )}
        </div>
      </div>

      <h3 className="text-lg font-semibold text-gray-700 mb-3">Histórico de activações</h3>
      <DataTable columns={actColumns} rows={activations} emptyMessage="Nenhuma activação" />
    </div>
  );
}
