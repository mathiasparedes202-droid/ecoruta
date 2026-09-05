import { API_BASE } from './env';

export async function authenticate(mode, data) {
  const endpoint = `${API_BASE}${mode === 'login' ? '/api/auth/login' : '/api/auth/register'}`;
  const response = await fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });

  const body = await response.text();
  let result = {};

  if (body) {
    try {
      result = JSON.parse(body);
    } catch {
      if (!response.ok) {
        throw new Error('El servidor respondió con un formato no válido. Verifica que la API esté en ejecución.');
      }
      throw new Error('La API respondió sin datos JSON válidos.');
    }
  }

  if (!response.ok) {
    throw new Error(result.message || 'No se pudo completar la solicitud');
  }

  if (!body) {
    throw new Error('La API respondió sin datos. Verifica que la API esté en ejecución.');
  }

  return result;
}
