import { useState, useEffect } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { get } from '../api';
import DataTable from '../components/DataTable';
import CopyButton from '../components/CopyButton';
import { formatDateTime } from '../format-date.js';
import {
  ADMIN_INSTALLATIONS_ROUTE,
  buildAdminCustomerDetailRoute,
  buildAdminLicenseDetailRoute,
} from '../panel-routes.js';

function Field({ label, value, copy }) {
  const display = value || '—';
  return (
    <div>
      <dt className="text-xs uppercase tracking-wide text-gray-500">{label}</dt>
      <dd className="mt-1 text-sm text-gray-900 break-all flex items-center gap-2">
        <span>{display}</span>
        {copy && value ? <CopyButton text={String(value)} /> : null}
      </dd>
    </div>
  );
}

export default function InstallationDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    get(`/installations/${id}`)
      .then(setData)
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <p className="text-gray-500">Carregando...</p>;
  if (!data?.installation) return <p className="text-red-500">Instalação não encontrada</p>;

  const inst = data.installation;
  const inventory = inst.inventory || {};
  const interfaces = Array.isArray(inventory.interfaces) ? inventory.interfaces : [];
  const pings = data.pings || [];

  const ifaceColumns = [
    { key: 'id', label: 'ID' },
    { key: 'descr', label: 'Nome', render: (r) => r.descr || '—' },
    { key: 'real', label: 'NIC', render: (r) => r.real || '—' },
    { key: 'ipv4', label: 'IPv4', render: (r) => r.ipv4 || '—' },
    { key: 'ipv6', label: 'IPv6', render: (r) => r.ipv6 || '—' },
  ];

  const pingColumns = [
    {
      key: 'created_at',
      label: 'Data',
      render: (r) => (r.created_at ? formatDateTime(r.created_at) : '—'),
    },
    { key: 'event', label: 'Evento' },
    { key: 'package_version', label: 'Layer7' },
    { key: 'egress_ip', label: 'IP público' },
    { key: 'result', label: 'Resultado' },
  ];

  return (
    <div>
      <button
        type="button"
        onClick={() => navigate(ADMIN_INSTALLATIONS_ROUTE)}
        className="text-sm text-brand-600 hover:underline mb-4 block"
      >
        &larr; Voltar às instalações
      </button>

      <h2 className="text-2xl font-bold text-gray-800 mb-1">
        {inst.fqdn || inst.hostname || `Instalação #${inst.id}`}
      </h2>
      <p className="text-sm text-gray-500 mb-6">
        Primeiro contacto {inst.first_seen_at ? formatDateTime(inst.first_seen_at) : '—'}
        {' · '}
        último {inst.last_seen_at ? formatDateTime(inst.last_seen_at) : '—'}
        {' · '}
        evento {inst.last_event || '—'}
      </p>

      <div className="bg-white rounded-lg shadow p-5 mb-6">
        <h3 className="font-semibold text-gray-800 mb-4">Identidade</h3>
        <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <Field label="Hostname" value={inst.hostname} />
          <Field label="Domínio" value={inst.domain} />
          <Field label="FQDN" value={inst.fqdn} />
          <Field label="uniqueid" value={inst.uniqueid} copy />
          <Field label="Série SMBIOS" value={inst.system_serial} copy />
          <Field label="hardware_id" value={inst.hardware_id} copy />
          <Field label="install_id" value={inst.install_id} copy />
        </dl>
      </div>

      <div className="bg-white rounded-lg shadow p-5 mb-6">
        <h3 className="font-semibold text-gray-800 mb-4">Rede</h3>
        <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
          <Field label="IP público (TLS)" value={inst.egress_ip} copy />
          <Field label="WAN IPv4" value={inst.wan_ipv4} />
          <Field label="WAN IPv6" value={inst.wan_ipv6} />
          <Field label="Gateway IPv4" value={inst.gateway_v4} />
        </dl>
        <h4 className="text-sm font-medium text-gray-700 mb-2">Interfaces locais</h4>
        <DataTable columns={ifaceColumns} rows={interfaces} emptyMessage="Sem interfaces no último ping." />
      </div>

      <div className="bg-white rounded-lg shadow p-5 mb-6">
        <h3 className="font-semibold text-gray-800 mb-4">Caixa</h3>
        <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <Field label="pfSense" value={inst.pfsense_version} />
          <Field label="Patch" value={inst.pfsense_version_patch} />
          <Field label="Plataforma" value={inst.platform} />
          <Field label="FreeBSD" value={inst.os_release} />
          <Field label="Layer7" value={inst.package_version} />
          <Field label="Modelo" value={inst.hw_model} />
          <Field label="CPU" value={inst.ncpu != null ? String(inst.ncpu) : ''} />
          <Field label="RAM (MB)" value={inst.mem_mb != null ? String(inst.mem_mb) : ''} />
        </dl>
      </div>

      <div className="bg-white rounded-lg shadow p-5 mb-6">
        <h3 className="font-semibold text-gray-800 mb-3">Licença cruzada</h3>
        {inst.license_id ? (
          <p className="text-sm text-gray-700">
            {inst.customer_id ? (
              <Link to={buildAdminCustomerDetailRoute(inst.customer_id)} className="text-brand-700 hover:underline">
                {inst.customer_name || 'Cliente'}
              </Link>
            ) : (
              <span>{inst.customer_name || 'Cliente'}</span>
            )}
            {' · '}
            <Link to={buildAdminLicenseDetailRoute(inst.license_id)} className="text-brand-700 hover:underline">
              {inst.license_key ? `${inst.license_key.slice(0, 12)}…` : `licença #${inst.license_id}`}
            </Link>
            {inst.license_status ? ` (${inst.license_status})` : ''}
          </p>
        ) : (
          <p className="text-sm text-gray-500">Ainda sem serial associado a este hardware_id.</p>
        )}
      </div>

      <div className="bg-white rounded-lg shadow p-5">
        <h3 className="font-semibold text-gray-800 mb-3">Histórico de pings</h3>
        <DataTable columns={pingColumns} rows={pings} emptyMessage="Sem histórico (heartbeat recente pode ter sido deduplicado)." />
      </div>
    </div>
  );
}
