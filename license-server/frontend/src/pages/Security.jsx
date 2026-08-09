import { useEffect, useState } from 'react';
import { get, post } from '../api';
import CopyButton from '../components/CopyButton';
import {
  AUTH_2FA_DISABLE_PATH,
  AUTH_2FA_ENABLE_PATH,
  AUTH_2FA_SETUP_PATH,
  AUTH_2FA_STATUS_PATH,
} from '../auth-paths.js';
import { usePermission } from '../use-permission.js';

export default function Security() {
  const canManageSelf = usePermission('security.self');
  const [enabled, setEnabled] = useState(false);
  const [setup, setSetup] = useState(null);
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  async function refreshStatus() {
    const data = await get(AUTH_2FA_STATUS_PATH);
    setEnabled(Boolean(data.totp_enabled));
  }

  useEffect(() => {
    if (!canManageSelf) {
      setLoading(false);
      return;
    }
    refreshStatus()
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [canManageSelf]);

  if (!canManageSelf) {
    return <p className="text-red-600">Sem permissão para gerir a segurança da conta.</p>;
  }

  async function handleSetup() {
    setError('');
    setMessage('');
    try {
      const data = await post(AUTH_2FA_SETUP_PATH, {});
      setSetup(data);
    } catch (err) {
      setError(err.message);
    }
  }

  async function handleEnable(event) {
    event.preventDefault();
    setError('');
    setMessage('');
    try {
      await post(AUTH_2FA_ENABLE_PATH, { code });
      setSetup(null);
      setCode('');
      setEnabled(true);
      setMessage('2FA activado.');
    } catch (err) {
      setError(err.message);
    }
  }

  async function handleDisable(event) {
    event.preventDefault();
    setError('');
    setMessage('');
    try {
      await post(AUTH_2FA_DISABLE_PATH, { password, code });
      setPassword('');
      setCode('');
      setEnabled(false);
      setMessage('2FA desactivado.');
    } catch (err) {
      setError(err.message);
    }
  }

  if (loading) {
    return <p className="text-gray-500">Carregando...</p>;
  }

  return (
    <div className="max-w-xl">
      <h2 className="text-2xl font-bold text-gray-800 mb-2">Segurança</h2>
      <p className="text-sm text-gray-600 mb-6">
        Autenticação em dois factores (TOTP) para o login admin.
      </p>

      {error && <div className="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-2 text-sm">{error}</div>}
      {message && <div className="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-2 text-sm">{message}</div>}

      <div className="bg-white rounded-lg shadow p-6 space-y-4">
        <p className="text-sm">
          Estado: <strong>{enabled ? 'Activado' : 'Desactivado'}</strong>
        </p>

        {!enabled && !setup && (
          <button
            type="button"
            onClick={handleSetup}
            className="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg"
          >
            Configurar 2FA
          </button>
        )}

        {setup && (
          <form onSubmit={handleEnable} className="space-y-3">
            <p className="text-sm text-gray-700">
              Adicione esta conta na app autenticadora (Google Authenticator, 1Password, etc.)
              usando o segredo ou o URI otpauth:
            </p>
            <div className="bg-gray-50 border rounded-lg p-3">
              <p className="text-xs text-gray-500 mb-1">Segredo</p>
              <code className="text-sm break-all">{setup.secret}</code>
              <div className="mt-1"><CopyButton text={setup.secret} label="Copiar segredo" /></div>
            </div>
            <div className="bg-gray-50 border rounded-lg p-3">
              <p className="text-xs text-gray-500 mb-1">URI otpauth</p>
              <code className="text-xs break-all">{setup.otpauth_url}</code>
              <div className="mt-1"><CopyButton text={setup.otpauth_url} label="Copiar URI" /></div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Código de confirmação</label>
              <input
                type="text"
                inputMode="numeric"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                required
                maxLength={6}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg"
              />
            </div>
            <button type="submit" className="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg">
              Activar 2FA
            </button>
          </form>
        )}

        {enabled && (
          <form onSubmit={handleDisable} className="space-y-3 border-t pt-4">
            <p className="text-sm text-gray-700">Para desactivar, confirme password + código actual.</p>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              placeholder="Password"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg"
            />
            <input
              type="text"
              inputMode="numeric"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              required
              maxLength={6}
              placeholder="Código 2FA"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg"
            />
            <button type="submit" className="px-4 py-2 bg-red-600 text-white text-sm rounded-lg">
              Desactivar 2FA
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
