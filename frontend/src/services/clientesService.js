import { API_BASE } from './env';

async function apiRequest(path, options = {}) {
  const token = localStorage.getItem('ecoruta_token');
  if (!token) {
    throw new Error('Sesión no autenticada. Iniciá sesión nuevamente.');
  }

  const response = await fetch(`${API_BASE}/api${path}`, {
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
    if (response.status === 401) {
      localStorage.removeItem('ecoruta_token');
      localStorage.removeItem('ecoruta_user');
      window.dispatchEvent(new Event('ecoruta-auth-required'));
    }
    throw new Error(data.message || 'No se pudo completar la operación.');
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