import { useState, useEffect } from 'react';
import { useParams, useNavigate, useLocation, useSearchParams } from 'react-router-dom';
import { get, post, del, download } from '../api';
import StatusBadge from '../components/StatusBadge';
import DataTable from '../components/DataTable';
import CopyButton from '../components/CopyButton';
import {
  formatLicenseEquipmentLabel,
  formatSkuLabel,
  isLicenseBound,
} from '../license-display.js';
import { formatCalendarDate, formatDateTime } from '../format-date.js';
import {
  buildLicenseActionConfirmMessage,
  buildLicenseDeliveryPack,
} from '../license-delivery-pack.js';
import {
  ADMIN_LICENSES_ROUTE,
  buildAdminCustomerDetailRoute,
  buildAdminLicenseDetailRoute,
  buildAdminLicenseEditRoute,
} from '../panel-routes.js';
import { usePermission } from '../use-permission.js';

const RENEW_OPTIONS = [
  { days: 30, label: '+30 dias' },
  { days: 90, label: '+90 dias' },
  { days: 365, label: '+1 ano' },
];

export default function LicenseDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const fromCustomerId = searchParams.get('from_customer') || '';
  const canUpdate = usePermission('licenses.update');
  const canRenewPerm = usePermission('licenses.renew');
  const canRevoke = usePermission('licenses.revoke');
  const canRebindPerm = usePermission('licenses.rebind');
  const canReplacePerm = usePermission('licenses.replace');
  const canDownloadPerm = usePermission('licenses.download');
  const canArchive = usePermission('licenses.archive');
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [renewing, setRenewing] = useState(false);
  const [renewBanner, setRenewBanner] = useState(null);
  const [showRebind, setShowRebind] = useState(false);
  const [rebindMode, setRebindMode] = useState('unbind');
  const [rebindReason, setRebindReason] = useState('');
  const [rebindHardwareId, setRebindHardwareId] = useState('');
  const [rebinding, setRebinding] = useState(false);
  const [rebindBanner, setRebindBanner] = useState(null);
  const [showReplace, setShowReplace] = useState(false);
  const [replaceReason, setReplaceReason] = useState('');
  const [replaceExpiry, setReplaceExpiry] = useState('');
  const [replacing, setReplacing] = useState(false);
  const [replaceBanner, setReplaceBanner] = useState(location.state?.replaceBanner || null);

  function load(signal) {
    setLoading(true);
    get(`/licenses/${id}`, signal ? { signal } : {})
      .then((payload) => {
        if (signal?.aborted) return;
        setData(payload);
      })
      .catch((err) => {
        if (err?.name === 'AbortError' || signal?.aborted) return;
        console.error(err);
      })
      .finally(() => {
        if (!signal?.aborted) setLoading(false);
      });
  }

  useEffect(() => {
    const controller = new AbortController();
    load(controller.signal);
    return () => controller.abort();
  }, [id]);

  useEffect(() => {
    if (location.state?.replaceBanner) {
      setReplaceBanner(location.state.replaceBanner);
      navigate(location.pathname, { replace: true, state: {} });
    }
  }, [location.state, location.pathname, navigate]);

  async function handleRevoke() {
    if (!confirm(buildLicenseActionConfirmMessage(
      'Revogar esta licença. O appliance deixa de receber check-in válido.',
      licenseForConfirm()
    ))) return;
    try {
      await post(`/licenses/${id}/revoke`, {});
      setRenewBanner(null);
      setRebindBanner(null);
      setReplaceBanner(null);
      load();
    } catch (err) {
      alert(err.message);
    }
  }

  function licenseForConfirm() {
    return data?.license || {};
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
    if (!confirm(buildLicenseActionConfirmMessage(
      `Renovar esta licença em ${days} dias.`,
      licenseForConfirm()
    ))) return;
    setRenewing(true);
    try {
      const previousExpiry = data?.license?.expiry;
      const renewed = await post(`/licenses/${id}/renew`, { days });
      setRenewBanner({
        days,
        expiry: renewed.expiry,
        previousExpiry,
        bound: Boolean(renewed.hardware_id),
      });
      load();
    } catch (err) {
      alert(err.message);
    } finally {
      setRenewing(false);
    }
  }

  async function handleRebind(event) {
    event.preventDefault();
    const warning = buildLicenseActionConfirmMessage(
      [
        'Trocar equipamento desta licença.',
        'O ficheiro .lic antigo pode continuar válido offline no equipamento antigo até à data de expiração + 14 dias de graça.',
      ].join(' '),
      licenseForConfirm()
    );
    if (!confirm(warning)) return;

    setRebinding(true);
    try {
      const previousHardwareId = data?.license?.hardware_id || null;
      const payload = {
        reason: rebindReason,
        mode: rebindMode,
      };
      if (rebindMode === 'set') {
        payload.new_hardware_id = rebindHardwareId.trim();
      }
      const rebound = await post(`/licenses/${id}/rebind`, payload);
      setRebindBanner({
        mode: rebindMode,
        bound: Boolean(rebound.hardware_id),
        previousHardwareId,
        nextHardwareId: rebound.hardware_id || null,
      });
      setShowRebind(false);
      setRebindReason('');
      setRebindHardwareId('');
      setRebindMode('unbind');
      load();
    } catch (err) {
      alert(err.message);
    } finally {
      setRebinding(false);
    }
  }

  async function handleReplace(event) {
    event.preventDefault();
    const warning = buildLicenseActionConfirmMessage(
      [
        'Criar NOVA chave e arquivar a licença revogada.',
        'Não desrevoga a chave antiga. O .lic antigo pode continuar válido offline até expiração + 14 dias.',
      ].join(' '),
      licenseForConfirm()
    );
    if (!confirm(warning)) return;

    setReplacing(true);
    try {
      const payload = { reason: replaceReason };
      if (replaceExpiry.trim()) {
        payload.expiry = replaceExpiry.trim();
      }
      const result = await post(`/licenses/${id}/replace`, payload);
      navigate(buildAdminLicenseDetailRoute(result.license.id), {
        replace: true,
        state: {
          replaceBanner: {
            previousId: result.previous.id,
            licenseKey: result.license.license_key,
          },
        },
      });
    } catch (err) {
      alert(err.message);
    } finally {
      setReplacing(false);
    }
  }

  if (loading) return <p className="text-gray-500">Carregando...</p>;
  if (!data) return <p className="text-red-500">Licença não encontrada</p>;

  const { license, activations, check_ins: checkIns = [] } = data;
  const bound = isLicenseBound(license);
  const canRenew = canRenewPerm && license.status !== 'revoked';
  const canRebind = canRebindPerm && license.status !== 'revoked' && bound;
  const canDownload = canDownloadPerm && license.status === 'active' && bound;
  const canReplace = canReplacePerm && license.status === 'revoked';
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
      <button
        onClick={() => navigate(
          fromCustomerId
            ? buildAdminCustomerDetailRoute(fromCustomerId)
            : ADMIN_LICENSES_ROUTE
        )}
        className="text-sm text-brand-600 hover:underline mb-4 block"
      >&larr; Voltar</button>

      {renewBanner && (
        <div className="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm mb-4">
          Licença renovada (+{renewBanner.days} dias). Expiração:{' '}
          {renewBanner.previousExpiry ? (
            <>
              <strong>{formatCalendarDate(renewBanner.previousExpiry)}</strong>
              {' → '}
            </>
          ) : null}
          <strong>{formatCalendarDate(renewBanner.expiry)}</strong>.
          {renewBanner.bound && canDownload ? (
            <>
              {' '}Esta licença já está vinculada a um pfSense: faça{' '}
              <button type="button" onClick={handleDownload} className="underline font-medium">
                download do .lic
              </button>{' '}
              actualizado e reinstale no equipamento se necessário.
            </>
          ) : renewBanner.bound ? (
            <> Já está vinculada a um equipamento — reinstale o .lic quando a licença estiver activa.</>
          ) : (
            <> Ainda por activar — a chave continua a mesma para o cliente activar no pfSense.</>
          )}
          <button type="button" onClick={() => setRenewBanner(null)} className="ml-3 text-green-700 underline">
            Fechar
          </button>
        </div>
      )}

      {rebindBanner && (
        <div className="bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3 text-sm mb-4">
          Troca de equipamento concluída e auditada.
          {rebindBanner.previousHardwareId || rebindBanner.nextHardwareId ? (
            <>
              {' '}Equipamento:{' '}
              <code className="text-xs">{rebindBanner.previousHardwareId ? `${String(rebindBanner.previousHardwareId).slice(0, 12)}…` : '—'}</code>
              {' → '}
              <code className="text-xs">{rebindBanner.nextHardwareId ? `${String(rebindBanner.nextHardwareId).slice(0, 12)}…` : 'por activar'}</code>.
            </>
          ) : null}
          {rebindBanner.mode === 'unbind' ? (
            <> A licença ficou por activar — o cliente deve correr <code>layer7d --activate CHAVE</code> no novo pfSense.</>
          ) : (
            <>
              {' '}Novo equipamento associado
              {canDownload ? (
                <>
                  {' '}— faça{' '}
                  <button type="button" onClick={handleDownload} className="underline font-medium">download do .lic</button>
                  {' '}e instale no pfSense.
                </>
              ) : (
                <> — reinstale o .lic quando a licença estiver activa.</>
              )}
            </>
          )}
          {' '}O .lic antigo pode continuar válido offline no equipamento anterior até à expiração + 14 dias de graça.
          <button type="button" onClick={() => setRebindBanner(null)} className="ml-3 underline">
            Fechar
          </button>
        </div>
      )}

      {replaceBanner && (
        <div className="bg-green-50 border border-green-200 text-green-900 rounded-lg px-4 py-3 text-sm mb-4">
          Substituição concluída. Licença #{replaceBanner.previousId} arquivada.
          Nova chave:{' '}
          <code className="break-all">{replaceBanner.licenseKey}</code>{' '}
          <CopyButton text={replaceBanner.licenseKey} />
          {' '}Entregar ao cliente e activar no pfSense. O .lic antigo pode
          continuar válido offline até expiry+grace — remover no appliance se
          precisar de efeito imediato.
          <button type="button" onClick={() => setReplaceBanner(null)} className="ml-3 underline">
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
              {formatLicenseEquipmentLabel(bound)}
            </span>
            <StatusBadge status={license.status} />
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div className="md:col-span-2">
            <span className="text-gray-500">Chave:</span>{' '}
            <code className="ml-2 break-all">{license.license_key}</code>{' '}
            <CopyButton text={license.license_key} />
            <CopyButton
              text={buildLicenseDeliveryPack(license)}
              label="Copiar pacote de entrega"
              className="ml-2 font-medium"
            />
          </div>
          <div>
            <span className="text-gray-500">Cliente:</span>{' '}
            {license.customer_id ? (
              <button
                type="button"
                onClick={() => navigate(buildAdminCustomerDetailRoute(license.customer_id))}
                className="ml-2 text-brand-700 hover:underline font-medium"
              >
                {license.customer_name || `Cliente #${license.customer_id}`}
              </button>
            ) : (
              <span className="ml-2">{license.customer_name || '—'}</span>
            )}
          </div>
          <div><span className="text-gray-500">Expira:</span> <span className="ml-2">{formatCalendarDate(license.expiry)}</span></div>
          <div><span className="text-gray-500">SKU:</span> <span className="ml-2">{formatSkuLabel(license.features)}</span></div>
          <div><span className="text-gray-500">Hardware ID:</span> <code className="ml-2 text-xs break-all">{license.hardware_id || 'Não activada'}</code></div>
          <div><span className="text-gray-500">Activada em:</span> <span className="ml-2">{license.activated_at ? formatDateTime(license.activated_at) : 'Nunca'}</span></div>
          <div><span className="text-gray-500">Criada em:</span> <span className="ml-2">{formatDateTime(license.created_at)}</span></div>
          <div>
            <span className="text-gray-500">Último check-in:</span>{' '}
            <span className="ml-2">
              {lastCheckIn
                ? `${new Date(lastCheckIn.created_at).toLocaleString('pt-BR')} (${lastCheckIn.result})`
                : 'Nunca'}
            </span>
          </div>
          {license.revoked_at && <div><span className="text-gray-500">Revogada em:</span> <span className="ml-2">{formatDateTime(license.revoked_at)}</span></div>}
          {license.notes && <div className="md:col-span-2"><span className="text-gray-500">Notas:</span> <span className="ml-2">{license.notes}</span></div>}
        </div>

        {bound && license.status === 'active' && (
          <p className="mt-4 text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
            Licença já vinculada a um equipamento: após alterar SKU ou data de expiração, faça download do .lic actualizado e reinstale no pfSense.
          </p>
        )}

        <div className="flex flex-wrap gap-3 mt-6">
          {canUpdate && license.status !== 'revoked' && (
            <button onClick={() => navigate(buildAdminLicenseEditRoute(id, { fromCustomerId: fromCustomerId || undefined }))} className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg transition-colors">
              Editar
            </button>
          )}
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
          {canRebind && (
            <button
              type="button"
              onClick={() => setShowRebind((current) => !current)}
              className="px-4 py-2 border border-amber-600 text-amber-800 hover:bg-amber-50 text-sm rounded-lg transition-colors"
            >
              {showRebind ? 'Cancelar troca' : 'Trocar equipamento'}
            </button>
          )}
          {canReplace && (
            <button
              type="button"
              onClick={() => setShowReplace((current) => !current)}
              className="px-4 py-2 border border-indigo-600 text-indigo-800 hover:bg-indigo-50 text-sm rounded-lg transition-colors"
            >
              {showReplace ? 'Cancelar substituição' : 'Substituir licença'}
            </button>
          )}
          {canRevoke && license.status === 'active' && (
            <button onClick={handleRevoke} className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
              Revogar
            </button>
          )}
          {canArchive && license.status !== 'active' && !canReplace && (
            <button onClick={async () => {
              if (!confirm(buildLicenseActionConfirmMessage('Arquivar esta licença.', license))) return;
              try { await del(`/licenses/${id}`); navigate(ADMIN_LICENSES_ROUTE); } catch (err) { alert(err.message); }
            }} className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
              Arquivar Licença
            </button>
          )}
          {canArchive && canReplace && (
            <button onClick={async () => {
              if (!confirm(buildLicenseActionConfirmMessage(
                'Arquivar esta licença revogada sem criar substituta.',
                license
              ))) return;
              try { await del(`/licenses/${id}`); navigate(ADMIN_LICENSES_ROUTE); } catch (err) { alert(err.message); }
            }} className="px-4 py-2 border border-red-600 text-red-700 hover:bg-red-50 text-sm rounded-lg transition-colors">
              Só arquivar
            </button>
          )}
          {canDownload && (
            <button onClick={handleDownload} className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg transition-colors">
              Download .lic
            </button>
          )}
        </div>

        {showRebind && canRebind && (
          <form onSubmit={handleRebind} className="mt-6 border border-amber-200 bg-amber-50 rounded-lg p-4 space-y-3">
            <p className="text-sm text-amber-900">
              Troca de equipamento (governada). O .lic antigo no pfSense anterior pode
              continuar válido offline até à <strong>data de expiração + 14 dias de graça</strong>.
              Preferir libertar o vínculo e deixar o cliente activar de novo no equipamento novo.
            </p>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Modo</label>
              <select
                value={rebindMode}
                onChange={(e) => setRebindMode(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              >
                <option value="unbind">Libertar — fica por activar (cliente activa de novo)</option>
                <option value="set">Associar — fixar ID de equipamento já conhecido</option>
              </select>
            </div>
            {rebindMode === 'set' && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">ID do novo equipamento (64 hex)</label>
                <input
                  type="text"
                  value={rebindHardwareId}
                  onChange={(e) => setRebindHardwareId(e.target.value)}
                  required
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono"
                />
              </div>
            )}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Motivo (obrigatório, ≥ 10 chars)</label>
              <textarea
                value={rebindReason}
                onChange={(e) => setRebindReason(e.target.value)}
                required
                minLength={10}
                rows={3}
                placeholder="Ex: Troca de NIC no Contacenter após falha de hardware"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              />
            </div>
            <button
              type="submit"
              disabled={rebinding}
              className="px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white text-sm rounded-lg disabled:opacity-50"
            >
              {rebinding ? 'A trocar...' : 'Confirmar troca de equipamento'}
            </button>
          </form>
        )}

        {showReplace && canReplace && (
          <form onSubmit={handleReplace} className="mt-6 border border-indigo-200 bg-indigo-50 rounded-lg p-4 space-y-3">
            <p className="text-sm text-indigo-950">
              Política P1d: <strong>não desrevoga</strong>. Cria chave nova (mesmo
              cliente/SKU), arquiva a revogada e audita. A chave antiga deixa de
              aparecer na lista; o .lic offline antigo pode ainda funcionar até
              expiry+grace.
            </p>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Nova expiração (opcional; default = da licença antiga)
              </label>
              <input
                type="date"
                value={replaceExpiry}
                onChange={(e) => setReplaceExpiry(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Motivo (obrigatório, ≥ 10 chars)</label>
              <textarea
                value={replaceReason}
                onChange={(e) => setReplaceReason(e.target.value)}
                required
                minLength={10}
                rows={3}
                placeholder="Ex: Substituição após revogação por chave comprometida"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              />
            </div>
            <button
              type="submit"
              disabled={replacing}
              className="px-4 py-2 bg-indigo-700 hover:bg-indigo-800 text-white text-sm rounded-lg disabled:opacity-50"
            >
              {replacing ? 'A substituir...' : 'Criar substituta e arquivar'}
            </button>
          </form>
        )}
      </div>

      <h3 className="text-lg font-semibold text-gray-700 mb-3">Histórico de activações</h3>
      <DataTable columns={actColumns} rows={activations} emptyMessage="Nenhuma activação" />

      <h3 className="text-lg font-semibold text-gray-700 mb-3 mt-8">Check-ins online</h3>
      <DataTable columns={checkInColumns} rows={checkIns} emptyMessage="Nenhum check-in registado" />
    </div>
  );
}
