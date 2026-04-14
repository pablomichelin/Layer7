export function handleInvalidAuthSession({
  clearAuthToken,
  notifyInvalidSession,
  redirectToLogin,
}) {
  clearAuthToken();
  notifyInvalidSession();
  redirectToLogin();
}
