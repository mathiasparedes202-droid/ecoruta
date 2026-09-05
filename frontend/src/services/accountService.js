import { API_BASE } from './env';

async function request(path, method, data) {
  const token = localStorage.getItem('ecoruta_token');
  const response = await fetch(`${API_BASE}${path}`, { method, headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, body: data ? JSON.stringify(data) : undefined });
  const result = await parseResponse(response);
  if (!response.ok) throw new Error(result.message || 'No se pudo completar la operación');
  return result;
}

async function parseResponse(response) {
  const text = await response.text();
  try { return text ? JSON.parse(text) : {}; }
  catch { return { message: `El servidor respondió con un error (${response.status})` }; }
}

export function updateProfile(data) { return request('/api/me', 'PATCH', data); }
export function updateCommerce(data) { return request('/api/me/comercio', 'PATCH', data); }
export function updatePassword(data) { return request('/api/me/password', 'POST', data); }
export function requestRecovery(correo) {
  return fetch(`${API_BASE}/api/auth/forgot-password`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ correo }) })
    .then(async (response) => { const result = await parseResponse(response); if (!response.ok) throw new Error(result.message || 'No se pudo solicitar la recuperación'); return result; });
}
export function resetAccount(data) { return request('/api/auth/reset-password', 'POST', data); }
