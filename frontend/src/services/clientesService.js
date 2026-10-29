import { API_BASE } from './env';
import { authenticatedFetch } from './authSession.js';

async function apiRequest(path, options = {}) {
  const token = localStorage.getItem('ecoruta_token');
  if (!token) {
    throw new Error('Sesión no autenticada. Iniciá sesión nuevamente.');
  }

  const separator = path.includes('?') ? '&' : '?';
  const url = `${API_BASE}/api${path}${separator}access_token=${encodeURIComponent(token)}`;
  const response = await authenticatedFetch(url, {
    ...options,
    credentials: 'include',
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
    const error = new Error(data.message || 'No se pudo completar la operación.');
    error.errors = data.errors || [];
    throw error;
  }

  return data;
}

export function fetchClientes() {
  return apiRequest('/clientes');
}

export function createCliente(data) {
  return apiRequest('/clientes', {
    method: 'POST',
    body: JSON.stringify(data)
  });
}

export function importClientes(clientes) {
  return apiRequest('/clientes/import', {
    method: 'POST',
    body: JSON.stringify({ clientes })
  });
}

export function updateCliente(id, data) {
  return apiRequest(`/clientes/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(data)
  });
}

export function deleteCliente(id) {
  return apiRequest(`/clientes/${id}`, {
    method: 'DELETE'
  });
}