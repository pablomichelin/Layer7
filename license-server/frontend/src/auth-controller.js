import {
  applyAuthenticatedSession,
  clearAuthenticatedSession,
  syncAuthenticatedSession,
} from './auth-session-state.js';
import {
  AUTH_LOGIN_PATH,
  AUTH_LOGOUT_PATH,
  AUTH_SESSION_PATH,
} from './auth-paths.js';
import { getAdminAuthRequestOptions } from './auth-request-options.js';

export async function bootstrapAuthSession({
  getSession,
  setAdmin,
  setSession,
  setLoading,
  isActive,
}) {
  try {
    const data = await getSession(AUTH_SESSION_PATH, getAdminAuthRequestOptions());
    if (!isActive()) {
      return null;
    }

    applyAuthenticatedSession(data, { setAdmin, setSession });
    return data;
  } catch {
    if (!isActive()) {
      return null;
    }

    clearAuthenticatedSession({ setAdmin, setSession });
    return null;
  } finally {
    if (isActive()) {
      setLoading(false);
    }
  }
}

export function clearAuthSessionState({ setAdmin, setSession }) {
  clearAuthenticatedSession({ setAdmin, setSession });
}

export async function loginWithPassword({
  postSession,
  email,
  password,
  setAdmin,
  setSession,
}) {
  const data = await postSession(AUTH_LOGIN_PATH, { email, password }, getAdminAuthRequestOptions());
  return syncAuthenticatedSession(data, { setAdmin, setSession });
}

export async function refreshAuthSession({
  getSession,
  setAdmin,
  setSession,
}) {
  const data = await getSession(AUTH_SESSION_PATH, getAdminAuthRequestOptions());
  return syncAuthenticatedSession(data, { setAdmin, setSession });
}

export async function logoutAuthSession({
  postSession,
  setAdmin,
  setSession,
}) {
  try {
    return await postSession(AUTH_LOGOUT_PATH, undefined, getAdminAuthRequestOptions());
  } finally {
    clearAuthenticatedSession({ setAdmin, setSession });
  }
}
