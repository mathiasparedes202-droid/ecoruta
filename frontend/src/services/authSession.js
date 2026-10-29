const TOKEN_KEY = 'ecoruta_token';
const USER_KEY = 'ecoruta_user';

export function getTokenExpiration(token) {
  try {
    const encodedPayload = token.split('.')[1];
    if (!encodedPayload) return null;

    const base64 = encodedPayload.replace(/-/g, '+').replace(/_/g, '/');
    const paddedBase64 = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
    const payload = JSON.parse(atob(paddedBase64));
    const expiration = Number(payload.exp);

    return Number.isFinite(expiration) ? expiration * 1000 : null;
  } catch {
    return null;
  }
}

export function clearAuthSession() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

export function endAuthSession() {
  clearAuthSession();
  window.dispatchEvent(new Event('ecoruta-auth-required'));
}

export async function authenticatedFetch(...args) {
  const response = await fetch(...args);

  if (response.status === 401 && localStorage.getItem(TOKEN_KEY)) {
    endAuthSession();
  }

  return response;
}
