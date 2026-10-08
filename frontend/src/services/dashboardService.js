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

export function fetchMetrics(desde, hasta) {
  const params = new URLSearchParams();
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  const query = params.toString() ? `?${params.toString()}` : '';
  return request(`/api/metrics${query}`);
}
