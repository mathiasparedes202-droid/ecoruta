import { API_BASE } from './env';
import { authenticatedFetch } from './authSession';

async function request(path, method = 'GET', data) {
  const token = localStorage.getItem('ecoruta_token');
  const response = await authenticatedFetch(`${API_BASE}${path}`, {
    method,
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: data ? JSON.stringify(data) : undefined
  });
  const text = await response.text();
  let result = {};
  if (text) {
    try {
      result = JSON.parse(text);
    } catch {
      result = { message: 'La API respondió con un formato no válido.' };
    }
  }
  if (!response.ok) throw new Error(result.message || 'No se pudo completar la operación.');
  return result;
}

const qs = (params = {}) => {
  const q = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== '' && v !== null && v !== undefined) q.set(k, String(v));
  }
  const s = q.toString();
  return s ? `?${s}` : '';
};

// Timbrados (admin)
export const fetchTimbrados = () => request('/api/timbrados');
export const fetchTimbradoVigente = () => request('/api/timbrados/vigente');
export const createTimbrado = (data) => request('/api/timbrados', 'POST', data);
export const updateTimbrado = (id, data) => request(`/api/timbrados/${id}`, 'PUT', data);

// Facturas (HU-09: filtros fecha/estado/número + descargas + anulación)
export const fetchFacturas = (filtros = {}) => request(`/api/facturas${qs(filtros)}`);
export const fetchFactura = (id) => request(`/api/facturas/${id}`);
export const emitirFacturaPedido = (idPedido, data = {}) => request(`/api/pedidos/${idPedido}/factura`, 'POST', data);
export const anularFactura = (id, motivo) => request(`/api/facturas/${id}/anular`, 'POST', { motivo });

// KuDE imprimible (misma sesión: el token va por access_token porque es una pestaña nueva)
export function kudeUrl(id) {
  const token = localStorage.getItem('ecoruta_token');
  return `${API_BASE}/api/facturas/${id}/kude`;
}
export function openKude(id) {
  window.open(kudeUrl(id), '_blank', 'noopener');
}

// HTML/PDF del motor factura-py (requieren login: se pasa access_token en query)
function authedUrl(path) {
  const token = localStorage.getItem('ecoruta_token');
  const sep = path.includes('?') ? '&' : '?';
  return `${API_BASE}${path}${sep}access_token=${encodeURIComponent(token || '')}`;
}
export function openFacturaHtml(id) {
  window.open(authedUrl(`/api/facturas/${id}/html`), '_blank', 'noopener');
}
export function openFacturaPdf(id) {
  window.open(authedUrl(`/api/facturas/${id}/pdf`), '_blank', 'noopener');
}
