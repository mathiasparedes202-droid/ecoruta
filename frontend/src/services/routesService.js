import { API_BASE } from './env';

export async function fetchRoutes() {
  const token = localStorage.getItem('ecoruta_token');
  const response = await fetch(`${API_BASE}/api/routes`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {}
  });
  if (!response.ok) {
    throw new Error('No se pudieron cargar las rutas');
  }
  return response.json();
}
