export function buildAuthContextValue({
  admin,
  session,
  loading,
  login,
  logout,
  refreshSession,
}) {
  return {
    admin,
    session,
    loading,
    isAuthenticated: Boolean(admin && session),
    login,
    logout,
    refreshSession,
  };
}
