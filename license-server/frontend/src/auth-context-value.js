export function buildAuthContextValue({
  admin,
  session,
  loading,
  login,
  loginTotp,
  logout,
  refreshSession,
}) {
  return {
    admin,
    session,
    loading,
    isAuthenticated: Boolean(admin && session),
    login,
    loginTotp,
    logout,
    refreshSession,
  };
}
