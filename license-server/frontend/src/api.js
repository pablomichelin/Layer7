const API_BASE = '/api';
let authToken = null;
import { AUTH_INVALID_EVENT } from './auth-events.js';
import { AUTH_LOGIN_PATH } from './auth-paths.js';
import { AUTH_SESSION_EXPIRED_MESSAGE } from './auth-messages.js';
import { ADMIN_LOGIN_ROUTE } from './panel-routes.js';
import {
  parseApiError,
  parseApiSuccess,
  shouldHandleInvalidSession,
} from './api-response.js';
import { handleInvalidAuthSession } from './api-auth-redirect.js';

function hasHeader(headers, name) {
  const expectedName = name.toLowerCase();
  return Object.keys(headers).some((headerName) => headerName.toLowerCase() === expectedName);
}

function getStoredAuthToken() {
  return authToken;
}

function clearStoredAuthToken() {
  authToken = null;
}

function emitInvalidSession() {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new Event(AUTH_INVALID_EVENT));
  }
}

function redirectToLogin() {
  if (typeof window !== 'undefined') {
    window.location.href = ADMIN_LOGIN_ROUTE;
  }
}

function shouldAttachAuthorizationHeader(path) {
  return path !== AUTH_LOGIN_PATH;
}

export async function api(path, options = {}) {
  const {
    raw = false,
    skipAuthRedirect = false,
    ...fetchOptions
  } = options;
  const headers = { ...fetchOptions.headers };

  if (fetchOptions.body !== undefined && !hasHeader(headers, 'Content-Type')) {
    headers['Content-Type'] = 'application/json';
  }

  if (shouldAttachAuthorizationHeader(path) && !hasHeader(headers, 'Authorization')) {
    const token = getStoredAuthToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }

  const res = await fetch(`${API_BASE}${path}`, {
    ...fetchOptions,
    headers,
    credentials: 'same-origin',
  });

  if (shouldHandleInvalidSession({ path, status: res.status, skipAuthRedirect })) {
    handleInvalidAuthSession({
      clearAuthToken: clearStoredAuthToken,
      notifyInvalidSession: emitInvalidSession,
      redirectToLogin,
    });
    throw new Error(AUTH_SESSION_EXPIRED_MESSAGE);
  }

  if (!res.ok) {
    throw new Error(await parseApiError(res));
  }

  if (raw) {
    return res;
  }

  return parseApiSuccess(res);
}

export function get(path, options = {}) {
  return api(path, { method: 'GET', ...options });
}

export function post(path, data, options = {}) {
  return api(path, {
    method: 'POST',
    body: data === undefined ? undefined : JSON.stringify(data),
    ...options,
  });
}

export function put(path, data, options = {}) {
  return api(path, {
    method: 'PUT',
    body: data === undefined ? undefined : JSON.stringify(data),
    ...options,
  });
}

export function del(path, options = {}) {
  return api(path, { method: 'DELETE', ...options });
}

export async function download(path, options = {}) {
  const res = await api(path, { method: 'GET', raw: true, ...options });
  return res.blob();
}

export function persistAuthToken(token) {
  authToken = typeof token === 'string' && token.trim() ? token : null;
}

export { clearStoredAuthToken };
