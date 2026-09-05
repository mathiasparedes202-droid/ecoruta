// Base de la API: apunta a la API de producción en Render si estamos en producción
export const API_BASE = (import.meta.env.VITE_API_URL && !import.meta.env.VITE_API_URL.includes('ecoruta-api.onrender.com'))
  ? import.meta.env.VITE_API_URL
  : (import.meta.env.PROD ? 'https://ecoruta-backend-app.onrender.com' : '');