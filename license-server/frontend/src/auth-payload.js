import { AUTH_SESSION_INVALID_MESSAGE } from './auth-messages.js';

export function isAuthenticatedSessionPayload(data) {
  return !!(data && data.admin && data.session);
}

export function assertAuthenticatedSessionPayload(data) {
  if (!isAuthenticatedSessionPayload(data)) {
    throw new Error(AUTH_SESSION_INVALID_MESSAGE);
  }

  return data;
}

export function normalizeAuthenticatedSessionPayload(data) {
  if (!isAuthenticatedSessionPayload(data)) {
    return {
      token: null,
      admin: null,
      session: null,
    };
  }

  return {
    token: data.token ?? null,
    admin: data.admin,
    session: data.session,
  };
}
