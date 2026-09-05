import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const CONCEPCION_CENTER = [-23.4025, -57.4443];
const LOCATION_MARKER = L.divIcon({
  className: 'cliente-marker',
  html: '<div class="cliente-marker-pin"></div>',
  iconSize: [22, 22],
  iconAnchor: [11, 11]
});

export default function CommercePage({ user }) {
  const mapRef = useRef(null);

  const lat = Number(user?.lat);
  const lng = Number(user?.lng);
  const hasCoords = isFinite(lat) && isFinite(lng) && lat !== 0 && lng !== 0;

  useEffect(() => {
    if (mapRef.current) return;

    const map = L.map('commerce-map').setView(
      hasCoords ? [lat, lng] : CONCEPCION_CENTER,
      hasCoords ? 15 : 12
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    if (hasCoords) {
      L.marker([lat, lng], { icon: LOCATION_MARKER }).addTo(map);
    }

    mapRef.current = map;

    return () => {
      map.remove();
      mapRef.current = null;
    };
  }, []);

  const rows = [
    ['Razón Social', user?.razon_social],
    ['RUC', user?.ruc],
    ['Dirección de Origen (retiro)', user?.direccion_comercio || user?.direccion_origen],
    ['Ciudad', user?.ciudad],
    ['Tarifa Base', user?.tarifa_base ? `${user.tarifa_base} ₲` : ''],
    ['Latitud', hasCoords ? lat.toFixed(6) : ''],
    ['Longitud', hasCoords ? lng.toFixed(6) : '']
  ];

  return (
    <section className="module-detail">
      <p className="eyebrow">Mi comercio</p>
      <h2>Datos de mi comercio</h2>
      <p>Información registrada de tu comercio y su punto de retiro. Para editarla, abre tu cuenta desde el menú superior.</p>

      <div className="operations-panel">
        <ul className="commerce-detail-list">
          {rows.map(([label, value]) => (
            <li key={label} className="commerce-detail-item">
              <span className="commerce-detail-label">{label}</span>
              <span className="commerce-detail-value">{value || '—'}</span>
            </li>
          ))}
        </ul>

        <div className="commerce-map-box">
          <p className="commerce-map-hint">Punto de retiro en el mapa.</p>
          <div id="commerce-map" style={{ height: '320px', width: '100%', borderRadius: '8px', zIndex: 0 }}></div>
        </div>
      </div>
    </section>
  );
}