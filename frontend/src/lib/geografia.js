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

export function boundedNominatim() {
  return L.Control.Geocoder.nominatim({
    geocodingQueryParams: {
      countrycodes: 'py',
      viewbox: `${PARAGUAY_BOUNDS[0][1]},${PARAGUAY_BOUNDS[1][0]},${PARAGUAY_BOUNDS[1][1]},${PARAGUAY_BOUNDS[0][0]}`,
      bounded: 1
    }
  });
}