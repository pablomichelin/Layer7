import { useEffect, useMemo, useState } from 'react';
import { get, post, put } from '../api';
import DataTable from '../components/DataTable';
import { usePermission } from '../use-permission.js';
import {
  PERMISSION_CATALOG,
  groupPermissionCatalog,
} from '../permissions.js';

const EMPTY_FORM = {
  name: '',
  email: '',
  password: '',
  permissions: ['licenses.read', 'customers.read', 'dashboard.read', 'security.self'],
};

const inputClass =
  'w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none disabled:bg-gray-100';

export default function Users() {
  const canManage = usePermission('users.manage');
  const [users, setUsers] = useState([]);
  const [catalog, setCatalog] = useState(PERMISSION_CATALOG);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [form, setForm] = useState(EMPTY_FORM);
  const [editingId, setEditingId] = useState(null);
  const groups = useMemo(() => groupPermissionCatalog(catalog), [catalog]);
  const groupEntries = useMemo(() => Object.entries(groups), [groups]);

  async function load() {
    setLoading(true);
    try {
      const [usersPayload, permissionsPayload] = await Promise.all([
        get('/users'),
        get('/users/permissions').catch(() => ({ permissions: PERMISSION_CATALOG })),
      ]);
      setUsers(usersPayload.users || []);
      if (permissionsPayload.permissions?.length) {
        setCatalog(permissionsPayload.permissions);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (canManage) {
      load();
    } else {
      setLoading(false);
    }
  }, [canManage]);

  function togglePermission(key) {
    setForm((current) => {
      const exists = current.permissions.includes(key);
      return {
        ...current,
        permissions: exists
          ? current.permissions.filter((item) => item !== key)
          : [...current.permissions, key],
      };
    });
  }

  function startEdit(user) {
    setEditingId(user.id);
    setForm({
      name: user.name || '',
      email: user.email || '',
      password: '',
      permissions: Array.isArray(user.permissions) ? user.permissions.filter((p) => p !== '*') : [],
      is_active: user.is_active !== false,
    });
    setMessage('');
    setError('');
  }

  function resetForm() {
    setEditingId(null);
    setForm(EMPTY_FORM);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');
    setMessage('');
    try {
      if (editingId) {
        const payload = {
          name: form.name,
          permissions: form.permissions,
          is_active: form.is_active !== false,
        };
        if (form.password) {
          payload.password = form.password;
        }
        await put(`/users/${editingId}`, payload);
        setMessage('Utilizador actualizado.');
      } else {
        await post('/users', {
          name: form.name,
          email: form.email,
          password: form.password,
          permissions: form.permissions,
        });
        setMessage('Técnico criado.');
      }
      resetForm();
      await load();
    } catch (err) {
      setError(err.message);
    }
  }

  if (!canManage) {
    return <p className="text-red-600">Sem permissão para gerir utilizadores.</p>;
  }

  if (loading) {
    return <p className="text-gray-500">Carregando...</p>;
  }

  const columns = [
    {
      key: 'name',
      label: 'Nome',
      render: (r) => <span className="font-medium text-gray-800">{r.name}</span>,
    },
    { key: 'email', label: 'Email', render: (r) => r.email },
    {
      key: 'type',
      label: 'Tipo',
      render: (r) => (
        <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${
          r.is_owner ? 'bg-brand-50 text-brand-800' : 'bg-gray-100 text-gray-700'
        }`}>
          {r.is_owner ? 'Owner' : 'Técnico'}
        </span>
      ),
    },
    {
      key: 'status',
      label: 'Estado',
      render: (r) => (
        <span className={r.is_active === false ? 'text-red-600' : 'text-green-700'}>
          {r.is_active === false ? 'Desactivo' : 'Activo'}
        </span>
      ),
    },
    {
      key: 'permissions',
      label: 'Permissões',
      render: (r) => {
        if (r.is_owner) return <span className="text-xs text-gray-500">Todas</span>;
        const list = r.permissions || [];
        return (
          <span className="text-xs text-gray-600" title={list.join(', ')}>
            {list.length} seleccionada{list.length === 1 ? '' : 's'}
          </span>
        );
      },
    },
    {
      key: 'actions',
      label: '',
      render: (r) => (
        <div className="flex gap-2" onClick={(e) => e.stopPropagation()}>
          {!r.is_owner && (
            <button type="button" onClick={() => startEdit(r)} className="text-xs text-brand-600 hover:underline">
              Editar
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-2xl font-bold text-gray-800">Utilizadores ({users.length})</h2>
      </div>

      {error && <div className="mb-3 bg-red-50 border border-red-200 text-red-700 rounded-lg px-3 py-2 text-sm">{error}</div>}
      {message && <div className="mb-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-3 py-2 text-sm">{message}</div>}

      <div className="mb-4">
        <DataTable columns={columns} rows={users} emptyMessage="Nenhum utilizador" />
      </div>

      <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow p-4">
        <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
          <h3 className="text-base font-semibold text-gray-800">
            {editingId ? 'Editar técnico' : 'Novo técnico'}
          </h3>
          {editingId && (
            <label className="inline-flex items-center gap-1.5 text-xs text-gray-700">
              <input
                type="checkbox"
                checked={form.is_active !== false}
                onChange={(e) => setForm((current) => ({ ...current, is_active: e.target.checked }))}
              />
              Conta activa
            </label>
          )}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Nome</label>
            <input
              required
              value={form.name}
              onChange={(e) => setForm((current) => ({ ...current, name: e.target.value }))}
              className={inputClass}
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Email</label>
            <input
              required={!editingId}
              type="email"
              disabled={Boolean(editingId)}
              value={form.email}
              onChange={(e) => setForm((current) => ({ ...current, email: e.target.value }))}
              className={inputClass}
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">
              Password{editingId ? ' (opcional)' : ''}
            </label>
            <input
              type="password"
              required={!editingId}
              minLength={10}
              value={form.password}
              onChange={(e) => setForm((current) => ({ ...current, password: e.target.value }))}
              className={inputClass}
              placeholder={editingId ? 'Manter actual' : ''}
            />
          </div>
        </div>

        <div className="border border-gray-100 rounded-lg bg-gray-50/60 p-3 mb-3">
          <p className="text-xs font-medium text-gray-600 mb-2">Permissões</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-2">
            {groupEntries.map(([groupName, items]) => (
              <div key={groupName}>
                <p className="text-[10px] uppercase tracking-wide text-gray-400 mb-1">{groupName}</p>
                <div className="flex flex-col gap-0.5">
                  {items.filter((item) => item.key !== 'users.manage').map((item) => (
                    <label
                      key={item.key}
                      className="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        className="rounded border-gray-300"
                        checked={form.permissions.includes(item.key)}
                        onChange={() => togglePermission(item.key)}
                      />
                      {item.label}
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="flex gap-2">
          <button
            type="submit"
            className="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors"
          >
            {editingId ? 'Guardar' : 'Criar técnico'}
          </button>
          {editingId && (
            <button
              type="button"
              onClick={resetForm}
              className="px-3 py-1.5 border border-gray-300 text-sm rounded-lg hover:bg-gray-50 transition-colors"
            >
              Cancelar
            </button>
          )}
        </div>
      </form>
    </div>
  );
}
