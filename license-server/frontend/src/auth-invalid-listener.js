import { AUTH_INVALID_EVENT } from './auth-events.js';

export function subscribeToInvalidAuthSession({
  clearAuthState,
  isActive,
  target = globalThis.window,
}) {
  if (!target?.addEventListener || !target?.removeEventListener) {
    return () => {};
  }

  function handleInvalidSession() {
    if (!isActive()) {
      return;
    }

    clearAuthState();
  }

  target.addEventListener(AUTH_INVALID_EVENT, handleInvalidSession);

  return () => {
    target.removeEventListener(AUTH_INVALID_EVENT, handleInvalidSession);
  };
}
