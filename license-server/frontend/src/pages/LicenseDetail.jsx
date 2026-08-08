import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { get, post, del, download } from '../api';
import StatusBadge from '../components/StatusBadge';
import DataTable from '../components/DataTable';
import CopyButton from '../components/CopyButton';
import { formatSkuLabel, isLicenseBound } from '../license-display.js';
import {
  ADMIN_LICENSES_ROUTE,
  buildAdminLicenseEditRoute,
} from '../panel-routes.js';

const RENEW_OPTIONS = [
  { days: 30, label: '+30 dias' },
  { days: 90, label: '+90 dias' },
  { days: 365, label: '+1 ano' },
];

export default function LicenseDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [renewing, setRenewing] = useState(false);
  const [renewBanner, setRenewBanner] = useState(null);

  function load() {
    setLoading(true);
    get(`/licenses/${id}`).then(setData).catch(console.error).finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, [id]);

  async function handleRevoke() {
    if (!confirm('Tem certeza que deseja revogar esta licença?')) return;
    try {
      await post(`/licenses/${id}/revoke`, {});
      setRenewBanner(null);
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

  async function handleRenew(days) {
    if (!confirm(`Renovar esta licença em ${days} dias?`)) return;
    setRenewing(true);
    try {
      const renewed = await post(`/licenses/${id}/renew`, { days });
      setRenewBanner({
        days,
        expiry: renewed.expiry,
        bound: Boolean(renewed.hardware_id),
      });
      load();
    } catch (err) {
      alert(err.message);
    } finally {
      setRenewing(false);
    }
  }

  if (loading) return <p className="text-gray-500">Carregando...</p>;
  if (!data) return <p className="text-red-500">Licença não encontrada</p>;

  const { license, activations, check_ins: checkIns = [] } = data;
  const bound = isLicenseBound(license);
  const canRenew = license.status !== 'revoked';
  const lastCheckIn = checkIns[0] || null;

  const actColumns = [
    { key: 'created_at', label: 'Data', render: (r) => new Date(r.created_at).toLocaleString('pt-BR') },
    { key: 'result', label: 'Resultado', render: (r) => <StatusBadge status={r.result} /> },
    { key: 'ip_address', label: 'IP' },
    { key: 'hardware_id', label: 'Hardware ID', render: (r) => r.hardware_id ? <code className="text-xs">{r.hardware_id.slice(0, 16)}...</code> : '—' },
    { key: 'error_message', label: 'Erro', render: (r) => r.error_message || '—' },
  ];

  const checkInColumns = [
    { key: 'created_at', label: 'Data', render: (r) => new Date(r.created_at).toLocaleString('pt-BR') },
    { key: 'result', label: 'Resultado', render: (r) => <StatusBadge status={r.result} /> },
    { key: 'ip_address', label: 'IP', render: (r) => r.ip_address || '—' },
    { key: 'hardware_id', label: 'Hardware ID', render: (r) => r.hardware_id ? <code className="text-xs">{r.hardware_id.slice(0, 16)}...</code> : '—' },
    { key: 'error_message', label: 'Erro', render: (r) => r.error_message || '—' },
  ];

  return (
    <div>
      <button onClick={() => navigate(ADMIN_LICENSES_ROUTE)} className="text-sm text-brand-600 hover:underline mb-4 block">&larr; Voltar</button>

      {renewBanner && (
        <div className="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm mb-4">
          Licença renovada (+{renewBanner.days} dias). Nova expiração:{' '}
          <strong>{new Date(renewBanner.expiry).toLocaleDateString('pt-BR')}</strong>.
          {renewBanner.bound ? (
            <>
              {' '}Appliance bindado: faça{' '}
              <button type="button" onClick={handleDownload} className="underline font-medium">
                download do .lic
              </button>{' '}
              actualizado e reinstale no pfSense se necessário.
            </>
          ) : (
            <> Ainda unbound — a chave continua a mesma para activação.</>
          )}
          <button type="button" onClick={() => setRenewBanner(null)} className="ml-3 text-green-700 underline">
            Fechar
          </button>
        </div>
      )}

      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <div className="flex items-start justify-between mb-4">
          <h2 className="text-xl font-bold text-gray-800">Detalhes da Licença</h2>
          <div className="flex gap-2">
            <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-medium ${
              bound ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700'
            }`}>
              {bound ? 'Bound' : 'Unbound'}
            </span>
            <StatusBadge status={license.status} />
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div className="md:col-span-2">
            <span className="text-gray-500">Chave:</span>{' '}
            <code className="ml-2 break-all">{license.license_key}</code>{' '}
            <CopyButton text={license.license_key} />
          </div>
          <div><span className="text-gray-500">Cliente:</span> <span className="ml-2">{license.customer_name || '—'}</span></div>
          <div><span className="text-gray-500">Expira:</span> <span className="ml-2">{new Date(license.expiry).toLocaleDateString('pt-BR')}</span></div>
          <div><span className="text-gray-500">SKU:</span> <span className="ml-2">{formatSkuLabel(license.features)}</span></div>
          <div><span className="text-gray-500">Hardware ID:</span> <code className="ml-2 text-xs break-all">{license.hardware_id || 'Não activada'}</code></div>
          <div><span className="text-gray-500">Activada em:</span> <span className="ml-2">{license.activated_at ? new Date(license.activated_at).toLocaleString('pt-BR') : 'Nunca'}</span></div>
          <div><span className="text-gray-500">Criada em:</span> <span className="ml-2">{new Date(license.created_at).toLocaleString('pt-BR')}</span></div>
          <div>
            <span className="text-gray-500">Último check-in:</span>{' '}
            <span className="ml-2">
              {lastCheckIn
                ? `${new Date(lastCheckIn.created_at).toLocaleString('pt-BR')} (${lastCheckIn.result})`
                : 'Nunca'}
            </span>
          </div>
          {license.revoked_at && <div><span className="text-gray-500">Revogada em:</span> <span className="ml-2">{new Date(license.revoked_at).toLocaleString('pt-BR')}</span></div>}
          {license.notes && <div className="md:col-span-2"><span className="text-gray-500">Notas:</span> <span className="ml-2">{license.notes}</span></div>}
        </div>

        <div className="flex flex-wrap gap-3 mt-6">
          <button onClick={() => navigate(buildAdminLicenseEditRoute(id))} className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg transition-colors">
            Editar
          </button>
          {canRenew && RENEW_OPTIONS.map((option) => (
            <button
              key={option.days}
              type="button"
              disabled={renewing}
              onClick={() => handleRenew(option.days)}
              className="px-4 py-2 border border-brand-600 text-brand-700 hover:bg-brand-50 text-sm rounded-lg transition-colors disabled:opacity-50"
            >
              Renovar {option.label}
            </button>
          ))}
          {license.status === 'active' && (
            <button onClick={handleRevoke} className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
              Revogar
            </button>
          )}
          {license.status !== 'active' && (
            <button onClick={async () => {
              if (!confirm('Arquivar esta licença?')) return;
              try { await del(`/licenses/${id}`); navigate(ADMIN_LICENSES_ROUTE); } catch (err) { alert(err.message); }
            }} className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
              Arquivar Licença
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

      <h3 className="text-lg font-semibold text-gray-700 mb-3 mt-8">Check-ins online</h3>
      <DataTable columns={checkInColumns} rows={checkIns} emptyMessage="Nenhum check-in registado" />
    </div>
  );
}
