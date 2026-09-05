import { API_BASE } from './env';

async function request(path, method = 'GET', data) {
  const token = localStorage.getItem('ecoruta_token');
  const response = await fetch(`${API_BASE}${path}`, {
    method,
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: data ? JSON.stringify(data) : undefined
  });
  const text = await response.text();
  const result = text ? JSON.parse(text) : {};
  if (!response.ok) throw new Error(result.message || 'No se pudo completar la operación');
  return result;
}

export function fetchUsers() { return request('/api/admin/users'); }
export function createUser(data) { return request('/api/admin/users', 'POST', data); }
export function updateUser(id, data) { return request(`/api/admin/users/${id}`, 'PATCH', data); }
export function fetchRepartidores() { return request('/api/admin/repartidores'); }
export function assignOrder(orderId, data) { return request(`/api/orders/${orderId}/assign`, 'PATCH', data); }
export function markOrderPaid(orderId, pagado, extra = {}) {
  return request(`/api/orders/${orderId}/pago`, 'PATCH', { pagado, ...extra });
}
export function updateOrder(orderId, data) {
  return request(`/api/orders/${orderId}`, 'PATCH', data);
}
export function fetchNotifications() { return request('/api/notificaciones'); }
export function markNotificationRead(id) { return request(`/api/notificaciones/${id}/leida`, 'PATCH'); }
export function markAllNotificationsRead() { return request('/api/notificaciones/leer-todas', 'POST'); }
