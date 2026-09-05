import { API_BASE } from './env';

async function request(path) {
  const token = localStorage.getItem('ecoruta_token');
  const response = await fetch(`${API_BASE}${path}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {}
  });
  const result = await response.json();
  if (!response.ok) throw new Error(result.message || 'No se pudo cargar la información');
  return result;
}

export function fetchOrders() {
  return request('/api/orders');
}

export function fetchMetrics() {
  return request('/api/metrics');
}
