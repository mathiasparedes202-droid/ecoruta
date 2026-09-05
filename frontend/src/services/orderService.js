import QRCode from 'qrcode';
import { API_BASE } from './env';

export const ORDERS_STORAGE_KEY = 'ecoruta_orders';

async function apiRequest(path, options = {}) {
  const token = localStorage.getItem('ecoruta_token');
  const response = await fetch(`${API_BASE}/api${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {})
    }
  });

  const text = await response.text();
  let data = {};
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = { message: 'La API respondió con un formato no válido.' };
    }
  }

  if (!response.ok) {
    throw new Error(data.message || 'No se pudo completar la operación.');
  }

  return data;
}

export function getStoredOrders() {
  try {
    const raw = localStorage.getItem(ORDERS_STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

export function saveStoredOrders(orders) {
  localStorage.setItem(ORDERS_STORAGE_KEY, JSON.stringify(orders));
  window.dispatchEvent(new Event('ecoruta-orders-updated'));
}

export async function fetchOrders() {
  return apiRequest('/orders');
}

export async function createOrder(data) {
  return apiRequest('/orders', {
    method: 'POST',
    body: JSON.stringify(data)
  });
}

export const DESTINATIONS_STORAGE_KEY = 'ecoruta_destinos';

export function getStoredDestinations() {
  try {
    const raw = localStorage.getItem(DESTINATIONS_STORAGE_KEY);
    const list = raw ? JSON.parse(raw) : [];
    return Array.isArray(list) ? list : [];
  } catch {
    return [];
  }
}

export function saveStoredDestination(dest) {
  const list = getStoredDestinations();
  const exists = list.some(
    (item) => Math.abs(item.lat - dest.lat) < 0.0001 && Math.abs(item.lng - dest.lng) < 0.0001
  );
  if (!exists) {
    list.unshift(dest);
  }
  localStorage.setItem(DESTINATIONS_STORAGE_KEY, JSON.stringify(list.slice(0, 20)));
  window.dispatchEvent(new Event('ecoruta_destinos_updated'));
}

export function removeStoredDestination(lat, lng) {
  const list = getStoredDestinations().filter(
    (item) => Math.abs(item.lat - lat) >= 0.0001 || Math.abs(item.lng - lng) >= 0.0001
  );
  localStorage.setItem(DESTINATIONS_STORAGE_KEY, JSON.stringify(list));
  window.dispatchEvent(new Event('ecoruta_destinos_updated'));
}

export function buildOrderQrPayload(order) {
  const id = order.id_pedido ?? order.id ?? '0';
  const comercio = order.id_comercio ?? 0;
  const repartidor = order.id_repartidor ?? 0;
  const params = new URLSearchParams({
    pedido: String(id),
    comercio: String(comercio),
    repartidor: String(repartidor),
    app: 'ecoruta'
  });

  return `ecoruta:pedido:${id}?${params.toString()}`;
}

export async function generateOrderQrDataUrl(order) {
  const payload = buildOrderQrPayload(order);

  return QRCode.toDataURL(payload, {
    margin: 4,
    width: 320,
    color: {
      dark: '#0D3B2A',
      light: '#FFFFFF'
    },
    errorCorrectionLevel: 'M',
    scale: 8
  });
}
