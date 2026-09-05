/// <reference types="vite/client" />

const envUrl = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/+$/, '');

export const API = envUrl || 'http://192.168.0.4:8000/api';
export const MAP_CENTER = { lat: -23.4004, lng: -57.433 }; // Asunción, PY