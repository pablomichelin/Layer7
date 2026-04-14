export function shouldHandleInvalidSession({
  path,
  status,
  skipAuthRedirect,
}) {
  return status === 401
    && !skipAuthRedirect
    && !String(path || '').startsWith('/auth/');
}

export async function parseApiError(res) {
  const body = await res.json().catch(() => ({}));
  return body.error || `Erro ${res.status}`;
}

export async function parseApiSuccess(res) {
  if (res.status === 204) {
    return null;
  }

  const contentType = res.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return res.json();
  }

  return res.text();
}
