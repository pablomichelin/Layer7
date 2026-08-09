export const PERMISSION_CATALOG = [
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

export function hasPermission(admin, permissionKey) {
  if (!admin || !permissionKey) {
    return false;
  }
  if (admin.is_owner) {
    return true;
  }
  const permissions = Array.isArray(admin.permissions) ? admin.permissions : [];
  return permissions.includes('*') || permissions.includes(permissionKey);
}

export function groupPermissionCatalog(catalog = PERMISSION_CATALOG) {
  const groups = {};
  for (const item of catalog) {
    if (!groups[item.group]) {
      groups[item.group] = [];
    }
    groups[item.group].push(item);
  }
  return groups;
}
