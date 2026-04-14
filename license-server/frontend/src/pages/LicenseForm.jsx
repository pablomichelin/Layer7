import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { get, post, put } from '../api';
import {
  buildLicenseFormState,
  buildLicenseSavePayload,
  isLicenseCustomerChangeBlocked,
} from '../license-form-state.js';
import {
  ADMIN_LICENSES_ROUTE,
  buildAdminLicenseDetailRoute,
} from '../panel-routes.js';

export default function LicenseForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEdit = Boolean(id);
  const [customers, setCustomers] = useState([]);
  const [form, setForm] = useState({
    customer_id: '',
    expiry: '',
    features: 'full',
    notes: '',
  });
  const [licenseState, setLicenseState] = useState(null);
  const [loadingInitialData, setLoadingInitialData] = useState(isEdit);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    let active = true;

    async function loadFormData() {
      try {
        const [customersResponse, licenseResponse] = await Promise.all([
          get('/customers?limit=200'),
          isEdit ? get(`/licenses/${id}`) : Promise.resolve(null),
        ]);

        if (!active) {
          return;
        }

        setCustomers(customersResponse.customers);

        if (licenseResponse) {
          const { license } = licenseResponse;
          setLicenseState(license);
          setForm(buildLicenseFormState(license));
        }
      } catch (err) {
        if (!active) {
          return;
        }

        setError(err.message);
      } finally {
        if (active) {
          setLoadingInitialData(false);
        }
      }
    }

    loadFormData();

    return () => {
      active = false;
    };
  }, [id, isEdit]);

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
      const payload = buildLicenseSavePayload(form);

      if (isEdit) {
        await put(`/licenses/${id}`, payload);
        navigate(buildAdminLicenseDetailRoute(id));
      } else {
        await post('/licenses', payload);
        navigate(ADMIN_LICENSES_ROUTE);
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

  if (loadingInitialData) {
    return <p className="text-gray-500">Carregando...</p>;
  }

  return (
    <div>
      <button onClick={() => navigate(isEdit ? buildAdminLicenseDetailRoute(id) : ADMIN_LICENSES_ROUTE)} className="text-sm text-brand-600 hover:underline mb-4 block">&larr; Voltar</button>

      <div className="bg-white rounded-lg shadow p-6 max-w-lg">
        <h2 className="text-xl font-bold text-gray-800 mb-6">{isEdit ? 'Editar Licença' : 'Nova Licença'}</h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          {error && <div className="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-2 text-sm">{error}</div>}

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
            <select
              name="customer_id"
              value={form.customer_id}
              onChange={handleChange}
              required
              disabled={customerChangeBlocked}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none disabled:bg-gray-100 disabled:text-gray-500"
            >
              <option value="">Seleccionar cliente...</option>
              {customers.map((customer) => (
                <option key={customer.id} value={customer.id}>{customer.name}</option>
              ))}
            </select>
            {customerChangeBlocked && (
              <p className="text-xs text-gray-500 mt-1">
                Licenças activadas/bindadas nao permitem trocar de cliente.
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Data de expiração</label>
            <input type="date" name="expiry" value={form.expiry} onChange={handleChange} required className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none" />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Features</label>
            <input type="text" name="features" value={form.features} onChange={handleChange} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none" />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea name="notes" value={form.notes} onChange={handleChange} rows="3" className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none" />
          </div>

          <button type="submit" disabled={loading} className="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-lg transition-colors disabled:opacity-50">
            {loading ? 'Salvando...' : isEdit ? 'Salvar Alterações' : 'Criar Licença'}
          </button>
        </form>
      </div>
    </div>
  );
}
