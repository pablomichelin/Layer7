import { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { get, post, put } from '../api';
import CopyButton from '../components/CopyButton';
import CustomerSelect from '../components/CustomerSelect.jsx';
import { buildLicenseDeliveryPack } from '../license-delivery-pack.js';
import {
  buildLicenseFormState,
  buildLicenseSavePayload,
  isKnownLicenseFeaturePreset,
  isLicenseCustomerChangeBlocked,
  LICENSE_FEATURE_PRESETS,
  LICENSE_FEATURES_DEFAULT,
} from '../license-form-state.js';
import {
  ADMIN_LICENSES_ROUTE,
  buildAdminCustomerDetailRoute,
  buildAdminLicenseDetailRoute,
} from '../panel-routes.js';

export default function LicenseForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const isEdit = Boolean(id);
  const presetCustomerId = searchParams.get('customer_id') || '';
  const fromCustomerId = searchParams.get('from_customer') || '';
  const [form, setForm] = useState({
    customer_id: presetCustomerId,
    expiry: '',
    features: LICENSE_FEATURES_DEFAULT,
    notes: '',
  });
  const [licenseState, setLicenseState] = useState(null);
  const [createdLicense, setCreatedLicense] = useState(null);
  const [loadingInitialData, setLoadingInitialData] = useState(isEdit);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    async function loadFormData() {
      try {
        if (!isEdit) {
          if (presetCustomerId) {
            setForm((current) => ({
              ...current,
              customer_id: presetCustomerId,
            }));
          }
          setLoadingInitialData(false);
          return;
        }

        const licenseResponse = await get(`/licenses/${id}`, { signal: controller.signal });
        if (controller.signal.aborted) return;
        const { license } = licenseResponse;
        if (license.status === 'revoked') {
          setError('Licenca revogada nao pode ser editada. Use substituicao na ficha.');
        }
        setLicenseState(license);
        setForm(buildLicenseFormState(license));
      } catch (err) {
        if (err?.name === 'AbortError' || controller.signal.aborted) return;
        setError(err.message);
      } finally {
        if (!controller.signal.aborted) {
          setLoadingInitialData(false);
        }
      }
    }

    loadFormData();
    return () => controller.abort();
  }, [id, isEdit, presetCustomerId]);

  function handleChange(event) {
    setForm((currentForm) => ({
      ...currentForm,
      [event.target.name]: event.target.value,
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setLoading(true);

    try {
      if (isEdit && licenseState?.status === 'revoked') {
        setError('Licenca revogada nao pode ser editada. Use substituicao na ficha.');
        setLoading(false);
        return;
      }

      const payload = buildLicenseSavePayload(form);

      if (isEdit) {
        await put(`/licenses/${id}`, payload);
        navigate(buildAdminLicenseDetailRoute(id, { fromCustomerId: fromCustomerId || undefined }));
      } else {
        const created = await post('/licenses', payload);
        setCreatedLicense(created);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }

  const customerChangeBlocked = isLicenseCustomerChangeBlocked({
    isEdit,
    license: licenseState,
  });
  const editBlocked = isEdit && licenseState?.status === 'revoked';

  const backRoute = isEdit
    ? buildAdminLicenseDetailRoute(id, { fromCustomerId: fromCustomerId || undefined })
    : (presetCustomerId
      ? buildAdminCustomerDetailRoute(presetCustomerId)
      : ADMIN_LICENSES_ROUTE);

  if (loadingInitialData) {
    return <p className="text-gray-500">Carregando...</p>;
  }

  if (createdLicense) {
    const customerId = createdLicense.customer_id || presetCustomerId;
    return (
      <div>
        <div className="bg-white rounded-lg shadow p-6 max-w-lg">
          <h2 className="text-xl font-bold text-gray-800 mb-2">Licença criada</h2>
          <p className="text-sm text-gray-600 mb-4">
            Guarde a chave. O cliente activa com{' '}
            <code className="text-xs bg-gray-100 px-1 rounded">layer7d --activate CHAVE</code>
          </p>
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
            <p className="text-xs text-gray-500 mb-1">Chave completa</p>
            <code className="text-sm break-all">{createdLicense.license_key}</code>
            <div className="mt-2 flex flex-wrap gap-3">
              <CopyButton text={createdLicense.license_key} label="Copiar chave" className="font-medium" />
              <CopyButton
                text={buildLicenseDeliveryPack(createdLicense)}
                label="Copiar pacote de entrega"
                className="font-medium"
              />
            </div>
          </div>
          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              onClick={() => navigate(buildAdminLicenseDetailRoute(createdLicense.id))}
              className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg"
            >
              Ver detalhe
            </button>
            {customerId ? (
              <button
                type="button"
                onClick={() => navigate(buildAdminCustomerDetailRoute(customerId))}
                className="px-4 py-2 border border-brand-600 text-brand-700 text-sm rounded-lg hover:bg-brand-50"
              >
                Voltar ao cliente
              </button>
            ) : null}
            <button
              type="button"
              onClick={() => navigate(ADMIN_LICENSES_ROUTE)}
              className="px-4 py-2 border border-gray-300 text-sm rounded-lg hover:bg-gray-50"
            >
              Lista de licenças
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div>
      <button onClick={() => navigate(backRoute)} className="text-sm text-brand-600 hover:underline mb-4 block">&larr; Voltar</button>

      <div className="bg-white rounded-lg shadow p-6 max-w-lg">
        <h2 className="text-xl font-bold text-gray-800 mb-6">{isEdit ? 'Editar Licença' : 'Nova Licença'}</h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          {error && <div className="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-2 text-sm">{error}</div>}

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
            <CustomerSelect
              name="customer_id"
              value={form.customer_id}
              onChange={handleChange}
              required
              disabled={customerChangeBlocked || editBlocked}
            />
            {customerChangeBlocked && (
              <p className="text-xs text-gray-500 mt-1">
                Licenças activadas/bindadas nao permitem trocar de cliente.
              </p>
            )}
            {!isEdit && presetCustomerId ? (
              <p className="text-xs text-gray-500 mt-1">
                Cliente pré-seleccionado a partir da ficha.
              </p>
            ) : null}
            {isEdit && licenseState?.hardware_id ? (
              <p className="text-xs text-amber-700 mt-1">
                Após mudar SKU ou data de expiração numa licença já vinculada a um equipamento, faça download do .lic e reinstale no pfSense.
              </p>
            ) : null}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Data de expiração</label>
            <input type="date" name="expiry" value={form.expiry} onChange={handleChange} required disabled={editBlocked} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none disabled:bg-gray-100" />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Features (SKU)</label>
            <select
              name="features"
              value={form.features}
              onChange={handleChange}
              disabled={editBlocked}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none disabled:bg-gray-100"
            >
              {LICENSE_FEATURE_PRESETS.map((preset) => (
                <option key={preset.value} value={preset.value}>{preset.label}</option>
              ))}
              {!isKnownLicenseFeaturePreset(form.features) && form.features ? (
                <option value={form.features}>
                  Legado / actual: {form.features}
                </option>
              ) : null}
            </select>
            <p className="text-xs text-gray-500 mt-1">
              ADR-0025: CSV ≤ 63 bytes. Legado <code>full</code> normaliza para <code>base</code> (T1).
              Identity/MITM exigem reemissão explícita.
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea name="notes" value={form.notes} onChange={handleChange} rows="3" disabled={editBlocked} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none disabled:bg-gray-100" />
          </div>

          <button type="submit" disabled={loading || editBlocked} className="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-lg transition-colors disabled:opacity-50">
            {loading ? 'Salvando...' : isEdit ? 'Salvar Alterações' : 'Criar Licença'}
          </button>
        </form>
      </div>
    </div>
  );
}
