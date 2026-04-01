const API_BASE = '/api';

function emitInvalidSession() {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new Event('layer7:auth-invalid'));
  }
}

export async function api(path, options = {}) {
  const {
    raw = false,
    skipAuthRedirect = false,
    ...fetchOptions
  } = options;
  const headers = { ...fetchOptions.headers };

  if (fetchOptions.body !== undefined && !headers['Content-Type']) {
    headers['Content-Type'] = 'application/json';
  }

  const res = await fetch(`${API_BASE}${path}`, {
    ...fetchOptions,
    headers,
    credentials: 'same-origin',
  });

  if (res.status === 401 && !skipAuthRedirect && !path.startsWith('/auth/')) {
    emitInvalidSession();
    window.location.href = '/login';
    throw new Error('Sessao expirada');
  }

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(body.error || `Erro ${res.status}`);
  }

  if (raw) {
    return res;
  }

  if (res.status === 204) {
    return null;
  }

  const contentType = res.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    return res.json();
  }

  return res.text();
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
