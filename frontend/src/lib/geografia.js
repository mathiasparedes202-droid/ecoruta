import L from 'leaflet';
import 'leaflet-control-geocoder';

export const PARAGUAY_BOUNDS = [
  [-27.6, -62.7],
  [-19.2, -54.2]
];

export const PARAGUAY_CENTER = [-23.4025, -57.4443];

export function inParaguay(lat, lng) {
  return (
    lat >= PARAGUAY_BOUNDS[0][0] &&
    lat <= PARAGUAY_BOUNDS[1][0] &&
    lng >= PARAGUAY_BOUNDS[0][1] &&
    lng <= PARAGUAY_BOUNDS[1][1]
  );
}

// Distancia en línea recta (km) entre dos puntos. Matemática pura: funciona
// sin internet. Se usa como respaldo cuando el trazador de rutas (OSRM)
// no responde.
export function haversineKm(aLat, aLng, bLat, bLng) {
  const R = 6371;
  const rad = (d) => (d * Math.PI) / 180;
  const dLat = rad(bLat - aLat);
  const dLng = rad(bLng - aLng);
  const h =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(rad(aLat)) * Math.cos(rad(bLat)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
  return 2 * R * Math.asin(Math.sqrt(h));
}

// Las calles nunca son rectas: se estima la distancia vial como la recta × 1.3.
// Es una aproximación honesta para cobrar offline; el backend recalcula con
// sus reglas al recibir el pedido.
export const OFFLINE_ROAD_FACTOR = 1.3;

export function boundedNominatim() {
  return L.Control.Geocoder.nominatim({
    geocodingQueryParams: {
      countrycodes: 'py',
      viewbox: `${PARAGUAY_BOUNDS[0][1]},${PARAGUAY_BOUNDS[1][0]},${PARAGUAY_BOUNDS[1][1]},${PARAGUAY_BOUNDS[0][0]}`,
      bounded: 1
    }
  });
}