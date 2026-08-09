const PERMISSION_CATALOG = [
  { key: 'dashboard.read', group: 'Leitura', label: 'Ver dashboard' },
  { key: 'licenses.read', group: 'Licenças', label: 'Ver licenças' },
  { key: 'licenses.create', group: 'Licenças', label: 'Criar licença' },
  { key: 'licenses.update', group: 'Licenças', label: 'Editar licença' },
  { key: 'licenses.renew', group: 'Licenças', label: 'Renovar' },
  { key: 'licenses.revoke', group: 'Licenças', label: 'Revogar' },
  { key: 'licenses.rebind', group: 'Licenças', label: 'Trocar equipamento' },
  { key: 'licenses.replace', group: 'Licenças', label: 'Substituir' },
  { key: 'licenses.download', group: 'Licenças', label: 'Download .lic' },
  { key: 'licenses.archive', group: 'Licenças', label: 'Arquivar licença' },
  { key: 'licenses.export', group: 'Licenças', label: 'Exportar CSV' },
  { key: 'customers.read', group: 'Clientes', label: 'Ver clientes' },
  { key: 'customers.create', group: 'Clientes', label: 'Criar cliente' },
  { key: 'customers.update', group: 'Clientes', label: 'Editar cliente' },
  { key: 'customers.archive', group: 'Clientes', label: 'Arquivar cliente' },
  { key: 'audit.read', group: 'Leitura', label: 'Ver auditoria' },
  { key: 'search.read', group: 'Leitura', label: 'Busca global' },
  { key: 'security.self', group: 'Conta', label: 'Gerir 2FA próprio' },
  { key: 'users.manage', group: 'Admin', label: 'Gerir utilizadores' },
];

const PERMISSION_KEYS = new Set(PERMISSION_CATALOG.map((item) => item.key));
const OWNER_PERMISSIONS = ['*'];

function listPermissionCatalog() {
  return PERMISSION_CATALOG.map((item) => ({ ...item }));
}

function normalizePermissions(raw, { allowUsersManage = false } = {}) {
  if (!Array.isArray(raw)) {
    return [];
  }

  const normalized = [];
  const seen = new Set();

  for (const entry of raw) {
    if (typeof entry !== 'string') {
      continue;
    }
    const key = entry.trim();
    if (!PERMISSION_KEYS.has(key) || seen.has(key)) {
      continue;
    }
    if (key === 'users.manage' && !allowUsersManage) {
      continue;
    }
    seen.add(key);
    normalized.push(key);
  }

  return normalized;
}

function resolveAdminPermissions(admin) {
  if (!admin) {
    return [];
  }

  if (admin.is_owner) {
    return OWNER_PERMISSIONS;
  }

  if (Array.isArray(admin.permissions)) {
    return normalizePermissions(admin.permissions, { allowUsersManage: false });
  }

  if (typeof admin.permissions === 'string') {
    try {
      return normalizePermissions(JSON.parse(admin.permissions), { allowUsersManage: false });
    } catch {
      return [];
    }
  }

  return [];
}

function hasPermission(admin, permissionKey) {
  if (!admin || !permissionKey) {
    return false;
  }

  if (admin.is_owner) {
    return true;
  }

  const permissions = resolveAdminPermissions(admin);
  return permissions.includes('*') || permissions.includes(permissionKey);
}

function toPublicAdmin(adminRow) {
  if (!adminRow) {
    return null;
  }

  const isOwner = Boolean(adminRow.is_owner);
  const permissions = isOwner
    ? OWNER_PERMISSIONS
    : normalizePermissions(adminRow.permissions, { allowUsersManage: false });

  return {
    id: adminRow.id,
    email: adminRow.email,
    name: adminRow.name,
    is_owner: isOwner,
    is_active: adminRow.is_active !== false,
    permissions,
    totp_enabled: Boolean(adminRow.totp_enabled),
  };
}

module.exports = {
  OWNER_PERMISSIONS,
  PERMISSION_CATALOG,
  PERMISSION_KEYS,
  hasPermission,
  listPermissionCatalog,
  normalizePermissions,
  resolveAdminPermissions,
  toPublicAdmin,
};
