// Base de la API. En desarrollo queda vacío y usa el proxy de Vite (/api -> localhost:8000).
// En producción se setea al compilar: VITE_API_URL=https://tu-api.onrender.com
export const API_BASE = import.meta.env.VITE_API_URL || '';