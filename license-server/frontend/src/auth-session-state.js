import { clearStoredAuthToken, persistAuthToken } from './api.js';
import {
  assertAuthenticatedSessionPayload,
  normalizeAuthenticatedSessionPayload,
} from './auth-payload.js';

export function applyAuthenticatedSession(data, { setAdmin, setSession }) {
  const normalized = normalizeAuthenticatedSessionPayload(data);
  persistAuthToken(normalized.token);
  setAdmin(normalized.admin);
  setSession(normalized.session);
}

export function syncAuthenticatedSession(data, { setAdmin, setSession }) {
  applyAuthenticatedSession(data, { setAdmin, setSession });
  return assertAuthenticatedSessionPayload(data);
}

export function clearAuthenticatedSession({ setAdmin, setSession }) {
  clearStoredAuthToken();
  setAdmin(null);
  setSession(null);
}
