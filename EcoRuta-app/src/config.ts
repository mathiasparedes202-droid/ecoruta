/// <reference types="vite/client" />

const envUrl = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/+$/, '');

export const API = envUrl || (import.meta.env.PROD
	? 'https://ecoruta-backend-app.onrender.com/api'
	: '/api');
export const MAP_CENTER = { lat: -23.4004, lng: -57.433 }; 