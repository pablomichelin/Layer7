import { useAuth } from './auth';
import { hasPermission } from './permissions.js';

export function usePermission(permissionKey) {
  const { admin } = useAuth();
  return hasPermission(admin, permissionKey);
}

export function useCan(permissionKey) {
  return usePermission(permissionKey);
}
