import { useState, useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet-control-geocoder';
import 'leaflet/dist/leaflet.css';
import 'leaflet-control-geocoder/dist/Control.Geocoder.css';
import { updatePassword, updateProfile, updateCommerce } from '../services/accountService.js';

const CONCEPCION_CENTER = [-23.4025, -57.4443];
const LOCATION_MARKER = L.divIcon({
  className: 'cliente-marker',
  html: '<div class="cliente-marker-pin"></div>',
  iconSize: [22, 22],
  iconAnchor: [11, 11]
});

export default function AccountPanel({ user, forced = false, onUpdated }) {
  const [profile, setProfile] = useState({ nombre_completo: user.nombre_completo, correo: user.correo, telefono: user.telefono || '' });
  const [passwords, setPasswords] = useState({ contraseña_actual: '', nueva_contraseña: '' });
  const [commerce, setCommerce] = useState({
    razon_social: user?.razon_social || '',
    ruc: user?.ruc || '',
    direccion_origen: user?.direccion_comercio || user?.direccion_origen || '',
    ciudad: user?.ciudad || '',
    tarifa_base: user?.tarifa_base || '',
    lat: user?.lat || '',
    lng: user?.lng || ''
  });
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  const isComerciante = Number(user.id_rol) === 1;
  const mapRef = useRef(null);
  const markerRef = useRef(null);

  useEffect(() => {
    if (!isComerciante || mapRef.current) return;
    const container = document.getElementById('account-commerce-map');
    if (!container) return;

    const lat = Number(commerce.lat);
    const lng = Number(commerce.lng);
    const hasCoords = isFinite(lat) && isFinite(lng) && lat !== 0 && lng !== 0;

    const map = L.map(container).setView(
      hasCoords ? [lat, lng] : CONCEPCION_CENTER,
      hasCoords ? 15 : 12
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    if (hasCoords) {
      markerRef.current = L.marker([lat, lng], { icon: LOCATION_MARKER }).addTo(map);
    }

    map.on('click', (event) => {
      const { lat: clickLat, lng: clickLng } = event.latlng;
      setCommerce((prev) => ({ ...prev, lat: clickLat.toFixed(6), lng: clickLng.toFixed(6) }));
      if (markerRef.current) {
        markerRef.current.setLatLng([clickLat, clickLng]);
      } else {
        markerRef.current = L.marker([clickLat, clickLng], { icon: LOCATION_MARKER }).addTo(map);
      }
    });

    try {
      const geocoder = L.Control.geocoder({ defaultMarkGeocode: false, collapsed: false });
      geocoder.on('markgeocode', (result) => {
        const { lat: geoLat, lng: geoLng } = result.geocode.center;
        setCommerce((prev) => ({ ...prev, lat: geoLat.toFixed(6), lng: geoLng.toFixed(6) }));
        map.setView([geoLat, geoLng], 16);
        if (markerRef.current) {
          markerRef.current.setLatLng([geoLat, geoLng]);
        } else {
          markerRef.current = L.marker([geoLat, geoLng], { icon: LOCATION_MARKER }).addTo(map);
        }
      });
      map.addControl(geocoder);
    } catch (error) {
      console.warn('No se pudo inicializar el buscador de direcciones', error);
    }

    mapRef.current = map;

    return () => {
      map.remove();
      mapRef.current = null;
      markerRef.current = null;
    };
  }, [isComerciante]);

  async function saveProfile(event) {
    event.preventDefault(); setLoading(true); setMessage('');
    try { const result = await updateProfile(profile); onUpdated(result.user); setMessage('Datos personales actualizados.'); }
    catch (error) { setMessage(error.message); } finally { setLoading(false); }
  }

  async function savePassword(event) {
    event.preventDefault(); setLoading(true); setMessage('');
    try { await updatePassword(passwords); setPasswords({ contraseña_actual: '', nueva_contraseña: '' }); setMessage('Contraseña actualizada correctamente.'); onUpdated({ ...user, debe_cambiar_contraseña: 0 }); }
    catch (error) { setMessage(error.message); } finally { setLoading(false); }
  }

  async function saveCommerce(event) {
    event.preventDefault(); setLoading(true); setMessage('');
    try { const result = await updateCommerce(commerce); if (onUpdated && result.user) onUpdated(result.user); setMessage('Datos del comercio actualizados.'); }
    catch (error) { setMessage(error.message); } finally { setLoading(false); }
  }

  return <section className={`account-panel ${forced ? 'account-panel--forced' : ''}`}>
    <p className="eyebrow">{forced ? 'Primer acceso' : 'Mi cuenta'}</p>
    <h2>{forced ? 'Cambia tu contraseña para continuar' : 'Configuración de cuenta'}</h2>
    {forced && <p className="account-intro">Por seguridad, debes reemplazar la contraseña temporal antes de entrar al sistema.</p>}
    {!forced && <form className="account-form" onSubmit={saveProfile}>
      <h3>Datos personales</h3>
      <label>
        Nombre completo
        <input value={profile.nombre_completo} onChange={(event) => setProfile({ ...profile, nombre_completo: event.target.value })} required minLength="3" />
      </label>
      <label>
        Correo electrónico
        <input type="email" value={profile.correo} onChange={(event) => setProfile({ ...profile, correo: event.target.value })} required />
      </label>
      <label>
        Teléfono
        <input value={profile.telefono} onChange={(event) => setProfile({ ...profile, telefono: event.target.value })} />
      </label>
      <button type="submit" className="primary-action" disabled={loading}>
        Guardar datos
      </button>
    </form>}
    {!forced && isComerciante && <form className="account-form" onSubmit={saveCommerce}>
      <h3>Mi comercio</h3>
      <label>
        Razón Social
        <input value={commerce.razon_social} onChange={(event) => setCommerce({ ...commerce, razon_social: event.target.value })} required />
      </label>
      <label>
        RUC
        <input value={commerce.ruc} onChange={(event) => setCommerce({ ...commerce, ruc: event.target.value })} required />
      </label>
      <label>
        Dirección de Origen (retiro)
        <input value={commerce.direccion_origen} onChange={(event) => setCommerce({ ...commerce, direccion_origen: event.target.value })} required />
      </label>
      <label>
        Ciudad
        <input value={commerce.ciudad} onChange={(event) => setCommerce({ ...commerce, ciudad: event.target.value })} required />
      </label>
      <label>
        Tarifa Base (₲)
        <input type="number" value={commerce.tarifa_base} onChange={(event) => setCommerce({ ...commerce, tarifa_base: event.target.value })} required />
      </label>
      <label>
        Latitud
        <input value={commerce.lat} onChange={(event) => setCommerce({ ...commerce, lat: event.target.value })} readOnly placeholder="Haz clic en el mapa" />
      </label>
      <label>
        Longitud
        <input value={commerce.lng} onChange={(event) => setCommerce({ ...commerce, lng: event.target.value })} readOnly placeholder="Haz clic en el mapa" />
      </label>
      <p className="account-map-hint">
        Busca tu dirección en el buscador y haz clic en el mapa para fijar el punto de retiro.
      </p>
      <div id="account-commerce-map" style={{ height: '320px', width: '100%', borderRadius: '8px', zIndex: 0 }}></div>
      <button type="submit" className="primary-action" disabled={loading}>
        Guardar datos
      </button>
    </form>}
    <form className="account-form" onSubmit={savePassword}>
      <h3>{forced ? 'Nueva contraseña' : 'Cambiar contraseña'}</h3>
      <label>
        {forced ? 'Contraseña temporal' : 'Contraseña actual'}
        <input type="password" value={passwords.contraseña_actual} onChange={(event) => setPasswords({ ...passwords, contraseña_actual: event.target.value })} required />
      </label>
      <label>
        Nueva contraseña
        <input type="password" value={passwords.nueva_contraseña} onChange={(event) => setPasswords({ ...passwords, nueva_contraseña: event.target.value })} required minLength="8" />
      </label>
      <button type="submit" className="primary-action" disabled={loading}>
        Actualizar contraseña
      </button>
    </form>
    {message && <p className="auth-message">{message}</p>}
  </section>;
}