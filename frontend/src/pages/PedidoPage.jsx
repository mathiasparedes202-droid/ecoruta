import { useState, useRef, useEffect, useCallback, useMemo } from 'react';
import L from 'leaflet';
import 'leaflet-control-geocoder';
import 'leaflet/dist/leaflet.css';
import 'leaflet-control-geocoder/dist/Control.Geocoder.css';
import {
  ArrowLeft,
  MapPin,
  Zap,
  Package,
  Leaf,
  CheckCircle,
  Trash2,
  Star,
  Loader2,
  Box,
  UserRound,
  Plus,
  Banknote,
  Landmark
} from 'lucide-react';
import { createOrder, getStoredDestinations, saveStoredDestination, removeStoredDestination } from '../services/orderService.js';
import { fetchClientes } from '../services/clientesService.js';
import { PARAGUAY_BOUNDS, PARAGUAY_CENTER, inParaguay, boundedNominatim } from '../lib/geografia.js';
import { toastSuccess, toastError, alertInfo } from '../lib/feedback.js';

const DEFAULT_CO2_FACTOR = 0.180; // kg CO2 por km
const TARIFA_KM = 2500; // guaraníes por km
const TARIFA_KG = 1500; // guaraníes por kg
const TARIFA_DM3 = 250; // guaraníes por dm³ aforado (alto*ancho*largo / 1000)
const CONCEPCION_CENTER = [-23.4025, -57.4443];
const DESTINATIONS_KEY = 'ecoruta_destinos';

// Íconos de marcador por defecto de Leaflet (se pierden con bundlers)
const ICON_ORIGIN = L.divIcon({
  className: 'new-order-marker new-order-marker--origin',
  html: '<div class="new-order-marker-pin"></div>',
  iconSize: [20, 20],
  iconAnchor: [10, 10]
});
const ICON_DEST = L.divIcon({
  className: 'new-order-marker new-order-marker--dest',
  html: '<div class="new-order-marker-pin"></div>',
  iconSize: [20, 20],
  iconAnchor: [10, 10]
});

/**
 * Desglose de la tarifa ecológica. Debe coincidir con el cálculo del backend
 * (backend/src/services/orders_service.php -> calcularTarifaEcologica).
 */
function buildTarifa({ base, km, kg, alto, ancho, largo }) {
  const rows = [{ label: 'Tarifa base', monto: base }];
  const distanceKm = Number(km) || 0;
  const weightKg = Number(kg) || 0;
  if (distanceKm > 0) rows.push({ label: `Distancia (${distanceKm.toFixed(2)} km)`, monto: distanceKm * TARIFA_KM });
  if (weightKg > 0) rows.push({ label: `Peso (${weightKg} kg)`, monto: weightKg * TARIFA_KG });

  const a = Number(alto) || 0;
  const b = Number(ancho) || 0;
  const c = Number(largo) || 0;
  let volumenDm3 = 0;
  if (a > 0 && b > 0 && c > 0) {
    volumenDm3 = (a * b * c) / 1000;
    rows.push({ label: `Volumen aforado (${volumenDm3.toFixed(1)} dm³)`, monto: volumenDm3 * TARIFA_DM3 });
  }

  const total = rows.reduce((sum, row) => sum + row.monto, 0);
  return { rows, total: Math.round(total), volumenDm3 };
}

function Field({ label, required, error, children }) {
  return (
    <label className="new-order-field">
      <span>
        {label} {required && <span className="new-order-required">*</span>}
      </span>
      {children}
      {error && <small className="new-order-error">{error}</small>}
    </label>
  );
}

export default function NewOrderPage({ user, onBack, onNavigate }) {
  const commerceLat = Number(user?.lat);
  const commerceLng = Number(user?.lng);
  const hasCommerceCoords = isFinite(commerceLat) && isFinite(commerceLng) && commerceLat !== 0 && commerceLng !== 0;
  const urlBase = hasCommerceCoords ? [commerceLat, commerceLng] : CONCEPCION_CENTER;

  const baseTariff = Number(user?.tarifa_base) > 0 ? Number(user.tarifa_base) : 12000;

  const [form, setForm] = useState({
    direccionOrigen: user?.direccion_comercio || user?.direccion_origen || '',
    direccionDestino: '',
    destinatarioNombre: '',
    destinatarioTelefono: '',
    detallePaquete: '',
    pesoKg: '',
    observaciones: ''
  });

  const [dims, setDims] = useState({ alto: '', ancho: '', largo: '' });
  const [clientes, setClientes] = useState([]);
  const [selectedClientId, setSelectedClientId] = useState('');
  const [clientQuery, setClientQuery] = useState('');
  const [clientOpen, setClientOpen] = useState(false);
  const comboBoxRef = useRef(null);

  const originCoords = hasCommerceCoords ? { lat: commerceLat, lng: commerceLng } : null;
  const [destCoords, setDestCoords] = useState(null);

  const [estimated, setEstimated] = useState(null);
  const [calculating, setCalculating] = useState(false);
  const [errors, setErrors] = useState({});

  const [savedDestinations, setSavedDestinations] = useState(() => getStoredDestinations());

  const [metodoPago, setMetodoPago] = useState('efectivo');
  const [transferenciaRealizada, setTransferenciaRealizada] = useState(false);
  const [comprobante, setComprobante] = useState('');
  const [mixtoEfectivo, setMixtoEfectivo] = useState('');
  const [mixtoTransferencia, setMixtoTransferencia] = useState('');

  // Refs del mapa Leaflet (se inicializan una sola vez)
  const mapRef = useRef(null);
  const mapContainerRef = useRef(null);
  const originMarkerRef = useRef(null);
  const destMarkerRef = useRef(null);
  const routeLayerRef = useRef(null);
  const geocoderRef = useRef(null);

  // Cargar clientes del comercio
  useEffect(() => {
    async function loadClientes() {
      try {
        const data = await fetchClientes();
        setClientes(Array.isArray(data) ? data : data.clientes || []);
      } catch {
        setClientes([]);
      }
    }
    if (Number(user?.id_rol) === 1) loadClientes();
  }, [user?.id_rol]);

  // Desglose de tarifa calculado en vivo (depende del peso y de las dimensiones)
  const feeInfo = useMemo(
    () =>
      buildTarifa({
        base: baseTariff,
        km: estimated?._raw?.distanceKm ?? 0,
        kg: form.pesoKg,
        alto: dims.alto,
        ancho: dims.ancho,
        largo: dims.largo
      }),
    [baseTariff, form.pesoKg, dims.alto, dims.ancho, dims.largo, estimated]
  );

  // Clientes que coinciden con lo buscado en el combobox
  const filteredClientes = useMemo(() => {
    const q = clientQuery.trim().toLowerCase();
    if (!q) return clientes;
    return clientes.filter((c) =>
      (c.nombre || '').toLowerCase().includes(q) ||
      (c.direccion || '').toLowerCase().includes(q)
    );
  }, [clientes, clientQuery]);

  // Cerrar el desplegable de clientes al hacer clic fuera
  useEffect(() => {
    if (!clientOpen) return;
    const onDocClick = (ev) => {
      if (comboBoxRef.current && !comboBoxRef.current.contains(ev.target)) setClientOpen(false);
    };
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, [clientOpen]);

  // Inicializar mapa (una sola vez)
  useEffect(() => {
    const container = mapContainerRef.current;
    if (!container) return;

    const map = L.map(container, { maxBounds: PARAGUAY_BOUNDS, maxBoundsViscosity: 1 }).setView(urlBase, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Marcador de origen fijo
    if (originCoords) {
      originMarkerRef.current = L.marker([originCoords.lat, originCoords.lng], {
        icon: ICON_ORIGIN,
        interactive: false
      }).addTo(map);
    }

    // Capa para la ruta
    routeLayerRef.current = L.layerGroup().addTo(map);

    // Clic en el mapa -> elegir destino (solo dentro de Paraguay)
    map.on('click', (event) => {
      const { lat, lng } = event.latlng;
      if (!inParaguay(lat, lng)) {
        setErrors((e) => ({ ...e, direccionDestino: 'EcoRuta solo entrega dentro de Paraguay. Elegí un punto en el país.' }));
        return;
      }
      setErrors((e) => ({ ...e, direccionDestino: '' }));
      pickDestination(lat, lng, null);
    });

    // Buscador de dirección restringido a Paraguay
    try {
      const geocoder = L.Control.geocoder({
        geocoder: boundedNominatim(),
        defaultMarkGeocode: false,
        collapsed: false
      });
      geocoder.on('markgeocode', (result) => {
        const center = result.geocode.center;
        if (!inParaguay(center.lat, center.lng)) {
          setErrors((e) => ({ ...e, direccionDestino: 'EcoRuta solo entrega dentro de Paraguay. Elegí una dirección en el país.' }));
          return;
        }
        setErrors((e) => ({ ...e, direccionDestino: '' }));
        pickDestination(center.lat, center.lng, result.geocode.name);
        map.setView([center.lat, center.lng], 16);
      });
      map.addControl(geocoder);
      geocoderRef.current = geocoder;
    } catch (error) {
      console.warn('No se pudo inicializar el buscador de direcciones', error);
    }

    mapRef.current = map;

    return () => {
      map.remove();
      mapRef.current = null;
      originMarkerRef.current = null;
      destMarkerRef.current = null;
      routeLayerRef.current = null;
      geocoderRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Dibujar/actualizar marcador de destino y la ruta cuando cambian las coordenadas de destino
  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;

    if (destCoords) {
      if (destMarkerRef.current) {
        destMarkerRef.current.setLatLng([destCoords.lat, destCoords.lng]);
      } else {
        destMarkerRef.current = L.marker([destCoords.lat, destCoords.lng], { icon: ICON_DEST }).addTo(map);
      }
    }

    // Calcular ruta automáticamente si hay origen y destino
    if (originCoords && destCoords) {
      calculateRoute(originCoords, destCoords);
    } else {
      setEstimated(null);
    }
  }, [destCoords]);

  function clearRouteLayer() {
    if (routeLayerRef.current) routeLayerRef.current.clearLayers();
  }

  async function calculateRoute(from, to) {
    setCalculating(true);
    setEstimated(null);

    const url = `https://router.project-osrm.org/route/v1/driving/${from.lng},${from.lat};${to.lng},${to.lat}?overview=full&geometries=geojson`;

    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error('OSRM no disponible');
      const data = await res.json();
      const route = data?.routes?.[0];

      if (!route || typeof route.distance !== 'number') {
        throw new Error('No se encontró una ruta');
      }

      const distanceKm = route.distance / 1000;
      const co2Saved = distanceKm * DEFAULT_CO2_FACTOR;

      setEstimated({
        distancia: `${distanceKm.toFixed(2)} km`,
        co2: `${co2Saved.toFixed(3)} kg`,
        _raw: { distanceKm, co2Saved }
      });

      // Dibujar la polilínea de la ruta (geometría GeoJSON viene como [lng, lat])
      clearRouteLayer();
      if (route.geometry?.coordinates?.length) {
        const latLngs = route.geometry.coordinates.map(([lng, lat]) => [lat, lng]);
        L.polyline(latLngs, { color: '#059669', weight: 5, opacity: 0.85 }).addTo(routeLayerRef.current);

        // Ajustar el mapa para mostrar la ruta completa
        if (originCoords && destCoords) {
          mapRef.current.fitBounds([[originCoords.lat, originCoords.lng], [destCoords.lat, destCoords.lng]], { padding: [40, 40] });
        }
      }
    } catch (err) {
      setErrors((e) => ({ ...e, direccionDestino: 'No pudimos trazar la ruta. Mirá las ubicaciones y tu conexión, y volvé a intentar.' }));
    } finally {
      setCalculating(false);
    }
  }

  const pickDestination = useCallback((lat, lng, label) => {
    setDestCoords({ lat, lng });
    if (label) {
      setForm((f) => ({ ...f, direccionDestino: label }));
      setErrors((e) => ({ ...e, direccionDestino: '' }));
    }
  }, []);

  function selectCliente(clienteId) {
    const value = Number(clienteId);
    const cliente = clientes.find((c) => Number(c.id_cliente) === value);
    setSelectedClientId(clienteId);
    setClientOpen(false);
    if (!cliente) {
      setClientQuery('');
      setForm((f) => ({ ...f, destinatarioNombre: '', destinatarioTelefono: '' }));
      return;
    }
    setClientQuery(cliente.nombre);
    setForm((f) => ({
      ...f,
      direccionDestino: cliente.direccion,
      destinatarioNombre: cliente.nombre,
      destinatarioTelefono: cliente.telefono || ''
    }));
    setErrors((e) => ({ ...e, direccionDestino: '', cliente: '' }));
    pickDestination(Number(cliente.lat), Number(cliente.lng), cliente.direccion);
    if (mapRef.current) mapRef.current.setView([Number(cliente.lat), Number(cliente.lng)], 15);
  }

  function loadSavedDestination(d) {
    pickDestination(d.lat, d.lng, d.label);
    if (mapRef.current) {
      mapRef.current.setView([d.lat, d.lng], 15);
    }
  }

  function handleRemoveSaved(ev, d) {
    ev.stopPropagation();
    removeStoredDestination(d.lat, d.lng);
    setSavedDestinations(getStoredDestinations());
  }

  const set = (key, value) => {
    setForm((currentForm) => ({ ...currentForm, [key]: value }));
    if (errors[key]) setErrors((currentErrors) => ({ ...currentErrors, [key]: '' }));
  };

  const setDim = (key, value) => {
    setDims((d) => ({ ...d, [key]: value }));
  };

  function validate() {
    const e = {};
    if (!form.direccionOrigen.trim()) e.direccionOrigen = 'La dirección de origen es obligatoria';
    if (!destCoords) {
      e.direccionDestino = 'Selecciona el destino en el mapa';
    } else if (!inParaguay(destCoords.lat, destCoords.lng)) {
      e.direccionDestino = 'EcoRuta solo entrega dentro de Paraguay';
    }

    const peso = Number(form.pesoKg);
    if (!form.detallePaquete.trim()) e.detallePaquete = 'Describe el contenido del paquete';
    if (!peso || peso <= 0 || peso > 50) e.pesoKg = 'Peso válido entre 0.1 y 50 kg';

    const a = Number(dims.alto) || 0;
    const b = Number(dims.ancho) || 0;
    const c = Number(dims.largo) || 0;
    if ((a > 0 || b > 0 || c > 0) && !(a > 0 && b > 0 && c > 0)) {
      e.dimensiones = 'Completa las tres dimensiones de la caja o dejá las tres en blanco';
    }

    if (clientes.length > 0 && !selectedClientId) {
      e.cliente = 'Buscá y seleccioná un cliente registrado en la lista';
    }

    if (metodoPago === 'transferencia') {
      if (transferenciaRealizada && String(comprobante).trim().length < 5) {
        e.comprobante = 'Anotá el número de comprobante (mínimo 5 caracteres)';
      }
    } else if (metodoPago === 'mixto') {
      const efectivo = Number(mixtoEfectivo);
      const transferencia = Number(mixtoTransferencia);
      if (!(efectivo > 0) || !(transferencia > 0) || Math.abs(efectivo + transferencia - feeInfo.total) > 1) {
        e.mixto = `Los montos deben sumar la tarifa (₲ ${feeInfo.total.toLocaleString('es-PY')})`;
      }
      if (transferencia > 0 && String(comprobante).trim().length < 5) {
        e.comprobante = 'Anotá el número de comprobante de la transferencia';
      }
    }

    return e;
  }

  async function handleSubmit() {
    if (!destCoords) {
      setErrors((e) => ({ ...e, direccionDestino: 'Selecciona el destino en el mapa' }));
      alertInfo('Elegí el destino en el mapa', 'Así sabemos a dónde va la entrega.');
      return;
    }
    if (!inParaguay(destCoords.lat, destCoords.lng)) {
      setErrors((e) => ({ ...e, direccionDestino: 'EcoRuta solo entrega dentro de Paraguay' }));
      alertInfo('Destino fuera de Paraguay', 'Mové el punto hacia Paraguay.');
      return;
    }
    if (!estimated) {
      alertInfo('Un momentito', 'Dejá que calculemos la distancia y la tarifa antes de confirmar.');
      return;
    }
    if (!form.detallePaquete.trim() || !form.pesoKg || Number(form.pesoKg) <= 0 || Number(form.pesoKg) > 50) {
      alertInfo('Faltan datos del paquete', 'Describí el contenido y poné un peso entre 0.1 y 50 kg.');
      return;
    }

    const e = validate();
    if (Object.keys(e).length > 0) {
      setErrors(e);
      alertInfo('Revisá los campos marcados en rojo', 'Faltan completar algunos datos del pedido.');
      return;
    }

    try {
      const destinoTexto = destCoords
        ? `${form.direccionDestino || 'Destino seleccionado'} (${destCoords.lat.toFixed(5)}, ${destCoords.lng.toFixed(5)})`
        : form.direccionDestino;

      await createOrder({
        id_comercio: user?.id_comercio,
        id_usuario: user?.id_usuario,
        direccion_origen: form.direccionOrigen,
        direccion_destino: destinoTexto,
        dest_lat: destCoords.lat,
        dest_lng: destCoords.lng,
        detalle_paquete: form.detallePaquete,
        peso_kg: Number(form.pesoKg),
        alto_cm: Number(dims.alto) || undefined,
        ancho_cm: Number(dims.ancho) || undefined,
        largo_cm: Number(dims.largo) || undefined,
        destinatario_nombre: form.destinatarioNombre.trim() || undefined,
        destinatario_telefono: form.destinatarioTelefono.trim() || undefined,
        distancia_km: estimated._raw.distanceKm,
        tarifa_ecologica: feeInfo.total,
        co2_ahorrado_kg: estimated._raw.co2Saved,
        metodo_pago: metodoPago,
        pagado: metodoPago === 'transferencia' && transferenciaRealizada,
        observaciones: form.observaciones,
        ...(metodoPago === 'transferencia' ? { comprobante_transferencia: String(comprobante).trim() || undefined } : {}),
        ...(metodoPago === 'mixto'
          ? {
              monto_efectivo: Number(mixtoEfectivo),
              monto_transferencia: Number(mixtoTransferencia),
              comprobante_transferencia: String(comprobante).trim() || undefined
            }
          : {}),
        ...(selectedClientId ? { id_cliente: Number(selectedClientId) } : {})
      });

      saveStoredDestination({
        label: form.direccionDestino || 'Destino',
        lat: destCoords.lat,
        lng: destCoords.lng
      });
      setSavedDestinations(getStoredDestinations());

      await toastSuccess('¡Entrega registrada!', 'Ya quedó a la vista del equipo para asignarle repartidor.').then(() => onBack());
    } catch (error) {
      await toastError('No pudimos registrar la entrega', error.message || 'Volvé a intentarlo.');
    }
  }

  return (
    <div className="new-order">
      <div className="new-order__header">
        <button type="button" onClick={onBack} className="new-order__back" aria-label="Volver"><ArrowLeft size={17} /></button>
        <div>
          <p className="eyebrow">Gestión de entregas</p>
          <h1>Nueva Solicitud de Entrega</h1>
          <p className="new-order__subtitle">Tocá el mapa para elegir adónde va la entrega (solo Paraguay). La ruta, la tarifa ecológica y el CO₂ ahorrado se calculan solos según la caja, el peso y la distancia.</p>
        </div>
      </div>

      <section className="new-order-panel">
        <div className="new-order-panel__title">
          <div className="new-order-icon new-order-icon--origin"><MapPin size={17} /></div>
          <div>
            <h2>Información del Origen</h2>
            <span>¿Dónde se recogerá el paquete?</span>
          </div>
        </div>

        <Field label="Dirección de origen" required error={errors.direccionOrigen}>
          <input
            className={`new-order-input ${errors.direccionOrigen ? 'new-order-input--error' : ''}`}
            value={form.direccionOrigen}
            onChange={(e) => set('direccionOrigen', e.target.value)}
            placeholder="Calle, número, sector, ciudad"
          />
        </Field>

        <div className="new-order-info">
          <Zap size={14} />
          <span>
            Origen precargado con la ubicación de tu comercio ({hasCommerceCoords ? `${commerceLat.toFixed(4)}, ${commerceLng.toFixed(4)}` : 'sin coordenadas configuradas'}). Se marca en el mapa con un punto verde.
          </span>
        </div>
      </section>

      <section className="new-order-panel">
        <div className="new-order-panel__title">
          <div className="new-order-icon new-order-icon--destination"><MapPin size={17} /></div>
          <div>
            <h2>Información del Destino</h2>
            <span>¿Quién recibe y a qué ubicación? Haz clic en el mapa o busca la dirección.</span>
          </div>
        </div>

        {clientes.length > 0 && (
          <Field label="Cliente registrado" required error={errors.cliente}>
            <div className="new-order-combobox" ref={comboBoxRef}>
              <input
                className={`new-order-input ${errors.cliente ? 'new-order-input--error' : ''}`}
                value={clientQuery}
                onChange={(e) => {
                  setClientQuery(e.target.value);
                  setSelectedClientId('');
                  setClientOpen(true);
                  if (errors.cliente) setErrors((prev) => ({ ...prev, cliente: '' }));
                }}
                onFocus={() => setClientOpen(true)}
                placeholder="Buscá al cliente por nombre o dirección…"
                autoComplete="off"
              />
              {clientOpen && (
                <div className="new-order-combobox__list">
                  {filteredClientes.length === 0 ? (
                    <div className="new-order-combobox__empty">Ningún cliente coincide. Si borrás el texto podés dejarlo vacío y completar el destino a mano.</div>
                  ) : (
                    filteredClientes.map((cliente) => (
                      <button
                        type="button"
                        key={cliente.id_cliente}
                        className="new-order-combobox__item"
                        onClick={() => selectCliente(cliente.id_cliente)}
                      >
                        <UserRound size={15} />
                        <span>
                          <strong>{cliente.nombre}</strong>
                          <small>{cliente.direccion}</small>
                        </span>
                      </button>
                    ))
                  )}
                </div>
              )}
            </div>
          </Field>
        )}

        <div className="new-order-grid">
          <Field label="Nombre del destinatario">
            <input
              className="new-order-input"
              value={form.destinatarioNombre}
              onChange={(e) => set('destinatarioNombre', e.target.value)}
              placeholder="Quién recibe la entrega"
            />
          </Field>
          <Field label="Teléfono del destinatario">
            <input
              className="new-order-input"
              value={form.destinatarioTelefono}
              onChange={(e) => set('destinatarioTelefono', e.target.value)}
              placeholder="Ej: +595 981 123456"
            />
          </Field>
        </div>

        {savedDestinations.length > 0 && (
          <div className="new-order-saved">
            <div className="new-order-saved__title"><Star size={14} /> Destinos frecuentes</div>
            <div className="new-order-saved__list">
              {savedDestinations.map((d, idx) => (
                <button
                  key={`${d.lat}-${d.lng}-${idx}`}
                  type="button"
                  className="new-order-saved__chip"
                  onClick={() => loadSavedDestination(d)}
                >
                  <MapPin size={14} />
                  <span>{d.label}</span>
                  <Trash2
                    size={13}
                    className="new-order-saved__remove"
                    onClick={(ev) => handleRemoveSaved(ev, d)}
                  />
                </button>
              ))}
            </div>
          </div>
        )}

        <Field label="Dirección de destino" required error={errors.direccionDestino}>
          <input
            className={`new-order-input ${errors.direccionDestino ? 'new-order-input--error' : ''}`}
            value={form.direccionDestino}
            onChange={(e) => set('direccionDestino', e.target.value)}
            placeholder="Busca en el mapa o escribe la dirección"
            readOnly
          />
          <span className="new-order-hint">El mapa limita la selección a Paraguay; solo se entregan envíos dentro del país.</span>
        </Field>

        <div className="new-order-map-box">
          <p className="commerce-map-hint">Haz clic en el mapa u usa el buscador para fijar el destino. La ruta se dibuja automáticamente.</p>
          <div id="dest-map" ref={mapContainerRef} style={{ height: '360px', width: '100%', borderRadius: '8px', zIndex: 0 }}></div>
        </div>

        <div className="new-order-info">
          {calculating ? (
            <Loader2 size={14} className="new-order-spin" />
          ) : (
            <Leaf size={14} />
          )}
          <span>
            {calculating
              ? 'Calculando ruta...'
              : destCoords
                ? `Destino: ${destCoords.lat.toFixed(5)}, ${destCoords.lng.toFixed(5)}${inParaguay(destCoords.lat, destCoords.lng) ? ' · Paraguay' : ' · FUERA DE PARAGUAY'}`
                : 'Selecciona el punto de entrega en el mapa.'}
          </span>
        </div>

        {onNavigate && (
          <button type="button" className="new-order-client-link" onClick={() => onNavigate('clientes')}>
            <UserRound size={15} /> <Plus size={14} /> Registrar cliente con ubicación exacta en "Mis clientes"
          </button>
        )}
      </section>

      <section className="new-order-panel">
        <div className="new-order-panel__title">
          <div className="new-order-icon new-order-icon--package"><Banknote size={17} /></div>
          <div>
            <h2>Método de pago</h2>
            <span>¿Cómo se abonará la tarifa ecológica del envío? Se le informa al repartidor por la app si el pedido ya está pagado.</span>
          </div>
        </div>

        <div className="new-order-payment">
          <button
            type="button"
            className={`new-order-payment__option ${metodoPago === 'efectivo' ? 'new-order-payment__option--selected' : ''}`}
            onClick={() => setMetodoPago('efectivo')}
          >
            <Banknote size={20} />
            <div>
              <strong>Efectivo</strong>
              <span>El repartidor cobra al cliente cuando entrega el paquete.</span>
            </div>
          </button>
          <button
            type="button"
            className={`new-order-payment__option ${metodoPago === 'transferencia' ? 'new-order-payment__option--selected' : ''}`}
            onClick={() => setMetodoPago('transferencia')}
          >
            <Landmark size={20} />
            <div>
              <strong>Transferencia</strong>
              <span>Pago previo por transferencia bancaria; el repartidor solo entrega.</span>
            </div>
          </button>
          <button
            type="button"
            className={`new-order-payment__option ${metodoPago === 'mixto' ? 'new-order-payment__option--selected' : ''}`}
            onClick={() => setMetodoPago('mixto')}
          >
            <Banknote size={20} />
            <div>
              <strong>Mixto</strong>
              <span>Parte por transferencia y parte en efectivo al entregar.</span>
            </div>
          </button>
        </div>

        {metodoPago === 'transferencia' && (
          <div className="new-order-payment__transfer">
            <div className="new-order-payment__account">
              <Landmark size={18} />
              <div>
                <strong>Cuenta EcoRuta para transferencias</strong>
                <span>Banco: EcoRuta S.A. · Caja de Ahorro ¨140.000</span>
                <span>N° 800 123 45 67 · RUC 80000000-1</span>
              </div>
            </div>
            <label className="new-order-payment__checkbox">
              <input
                type="checkbox"
                checked={transferenciaRealizada}
                onChange={(e) => setTransferenciaRealizada(e.target.checked)}
              />
              <span>Ya realicé la transferencia. Confirmo el pago de este envío ahora.</span>
            </label>
            <span className="new-order-hint">Si marcás esta opción, el pedido nace como pagado y el repartidor lo verá así en su app.</span>
            <Field label="Número de comprobante" required={transferenciaRealizada} error={errors.comprobante}>
              <input
                className={`new-order-input ${errors.comprobante ? 'new-order-input--error' : ''}`}
                value={comprobante}
                onChange={(e) => {
                  setComprobante(e.target.value);
                  if (errors.comprobante) setErrors((prev) => ({ ...prev, comprobante: '' }));
                }}
                placeholder="Ej: TRX-20260905-1234"
              />
            </Field>
          </div>
        )}

        {metodoPago === 'mixto' && (
          <div className="new-order-payment__transfer">
            <div className="new-order-payment__account">
              <Landmark size={18} />
              <div>
                <strong>Cuenta EcoRuta para transferencias</strong>
                <span>Banco: EcoRuta S.A. · Caja de Ahorro ¨140.000</span>
                <span>N° 800 123 45 67 · RUC 80000000-1</span>
              </div>
            </div>
            <div className="new-order-mixto new-order-grid">
              <Field label="En efectivo" error={errors.mixto}>
                <input
                  className={`new-order-input ${errors.mixto ? 'new-order-input--error' : ''}`}
                  type="number"
                  min="0"
                  step="500"
                  value={mixtoEfectivo}
                  onChange={(e) => { setMixtoEfectivo(e.target.value); if (errors.mixto) setErrors((prev) => ({ ...prev, mixto: '' })); }}
                  placeholder="₲ a cobrar al entregar"
                />
              </Field>
              <Field label="Por transferencia" error={errors.mixto}>
                <input
                  className={`new-order-input ${errors.mixto ? 'new-order-input--error' : ''}`}
                  type="number"
                  min="0"
                  step="500"
                  value={mixtoTransferencia}
                  onChange={(e) => { setMixtoTransferencia(e.target.value); if (errors.mixto) setErrors((prev) => ({ ...prev, mixto: '' })); }}
                  placeholder="₲ a transferir"
                />
              </Field>
            </div>
            {(() => {
              const total = feeInfo.total || 0;
              const falta = total - (Number(mixtoEfectivo) || 0) - (Number(mixtoTransferencia) || 0);
              return (
                <span className={`new-order-hint ${errors.mixto ? 'new-order-hint--error' : ''}`}>
                  Tarifa: ₲ {total.toLocaleString('es-PY')} · Falta repartir: ₲ {Math.max(0, falta).toLocaleString('es-PY')}
                  <button type="button" className="new-order-mixto__split" onClick={() => {
                    const mitad = Math.round(total / 2 / 100) * 100;
                    setMixtoEfectivo(String(Math.max(0, total - mitad)));
                    setMixtoTransferencia(String(mitad));
                  }}>
                    Dividir a la mitad
                  </button>
                </span>
              );
            })()}
            {errors.mixto && <small className="new-order-error">{errors.mixto}</small>}
            <Field label="Número de comprobante de la transferencia" required error={errors.comprobante}>
              <input
                className={`new-order-input ${errors.comprobante ? 'new-order-input--error' : ''}`}
                value={comprobante}
                onChange={(e) => {
                  setComprobante(e.target.value);
                  if (errors.comprobante) setErrors((prev) => ({ ...prev, comprobante: '' }));
                }}
                placeholder="Ej: TRX-20260905-1234"
              />
            </Field>
            <span className="new-order-hint">Con pago mixto el pedido nace pendiente: falta cobrar la parte en efectivo al entregar.</span>
          </div>
        )}
      </section>

      <section className="new-order-panel">
        <div className="new-order-panel__title">
          <div className="new-order-icon new-order-icon--package"><Package size={17} /></div>
          <div>
            <h2>Información del Paquete</h2>
            <span>Contenido, peso y tamaño de la caja (afectan la tarifa ecológica)</span>
          </div>
        </div>

        <div className="new-order-grid">
          <Field label="Detalle del paquete" required error={errors.detallePaquete}>
            <input
              className={`new-order-input ${errors.detallePaquete ? 'new-order-input--error' : ''}`}
              value={form.detallePaquete}
              onChange={(e) => set('detallePaquete', e.target.value)}
              placeholder="Describe el contenido del paquete"
            />
          </Field>
          <Field label="Peso (kg)" required error={errors.pesoKg}>
            <input
              className={`new-order-input ${errors.pesoKg ? 'new-order-input--error' : ''}`}
              type="number"
              step="0.1"
              min="0.1"
              max="50"
              value={form.pesoKg}
              onChange={(e) => set('pesoKg', e.target.value)}
              placeholder="Ej: 2.5"
            />
          </Field>
        </div>

        <Field label="Tamaño de la caja (cm)" error={errors.dimensiones}>
          <div className="new-order-grid new-order-grid--dims">
            <input
              className={`new-order-input ${errors.dimensiones ? 'new-order-input--error' : ''}`}
              type="number" step="1" min="1"
              value={dims.alto}
              onChange={(e) => setDim('alto', e.target.value)}
              placeholder="Alto"
            />
            <input
              className={`new-order-input ${errors.dimensiones ? 'new-order-input--error' : ''}`}
              type="number" step="1" min="1"
              value={dims.ancho}
              onChange={(e) => setDim('ancho', e.target.value)}
              placeholder="Ancho"
            />
            <input
              className={`new-order-input ${errors.dimensiones ? 'new-order-input--error' : ''}`}
              type="number" step="1" min="1"
              value={dims.largo}
              onChange={(e) => setDim('largo', e.target.value)}
              placeholder="Largo"
            />
          </div>
          <small className="new-order-error">{errors.dimensiones}</small>
          {feeInfo.volumenDm3 > 0 && (
            <span className="new-order-hint">
              <Box size={13} /> Volumen aforado: {feeInfo.volumenDm3.toFixed(1)} dm³ = {dims.alto} × {dims.ancho} × {dims.largo} cm / 1000. Suma ₲ {(feeInfo.volumenDm3 * TARIFA_DM3).toLocaleString('es-PY')} a la tarifa.
            </span>
          )}
        </Field>

        <Field label="Observaciones">
          <textarea className="new-order-input new-order-textarea" rows={4} value={form.observaciones} onChange={(e) => set('observaciones', e.target.value)} placeholder="Instrucciones especiales de entrega..." />
        </Field>
      </section>

      <section className="new-order-panel new-order-estimation">
        <div className="new-order-panel__title new-order-panel__title--no-margin">
          <div className="new-order-icon new-order-icon--eco"><Leaf size={17} /></div>
          <div>
            <h2>Tarifa ecológica calculada</h2>
            <span>Se actualiza en vivo con el peso y el tamaño de la caja</span>
          </div>
        </div>

        {estimated ? (
          <div className="new-order-results">
            <div className="new-order-result"><span>Distancia estimada</span><strong>{estimated.distancia}</strong></div>
            <div className="new-order-result new-order-result--co2"><span>CO₂ ahorrado</span><strong>{estimated.co2}</strong></div>

            <div className="new-order-fee-breakdown">
              <div className="new-order-fee-breakdown__title">Desglose de la tarifa</div>
              {feeInfo.rows.map((row, idx) => (
                <div className="new-order-fee-breakdown__row" key={idx}>
                  <span>{row.label}</span>
                  <strong>₲ {row.monto.toLocaleString('es-PY')}</strong>
                </div>
              ))}
              <div className="new-order-fee-breakdown__total">
                <span>Tarifa ecológica</span>
                <strong>₲ {feeInfo.total.toLocaleString('es-PY')}</strong>
              </div>
            </div>
          </div>
        ) : (
          <div className="new-order-empty">
            <Leaf size={28} />
            <strong>Sin estimación todavía</strong>
            <p>Selecciona el destino en el mapa y se calcula automáticamente la distancia, la tarifa ecológica (base + km + peso + volumen) y el impacto ambiental.</p>
          </div>
        )}
      </section>

      <div className="new-order-actions">
        <button type="button" onClick={onBack} className="new-order-cancel">Cancelar</button>
        <button type="button" onClick={handleSubmit} className="primary-action new-order-submit"><CheckCircle size={16} />Confirmar solicitud</button>
      </div>
    </div>
  );
}