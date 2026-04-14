export function getAuthGateState({
  loading,
  isAuthenticated,
}) {
  if (loading) {
    return 'loading';
  }

  return isAuthenticated ? 'authenticated' : 'anonymous';
}
