import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Pedido } from './types/repartidor';
import { MAP_CENTER } from './config';

declare const L: any;

interface RepartidorMapProps {
  activePedido: Pedido | null;
  assignedOrders?: Pedido[];
  tipoVehiculo?: 'Bicicleta' | 'Vehículo Eléctrico';
  onConfirmDelivery?: (pedido: Pedido) => void;
  isActive?: boolean;
  allCoords?: [number, number][];
}

const RepartidorMap: React.FC<RepartidorMapProps> = ({ activePedido, assignedOrders = [], tipoVehiculo = 'Bicicleta', isActive = true, allCoords = [] }) => {
  const mapContainerRef = useRef<HTMLDivElement>(null);
  const mapInstanceRef = useRef<any>(null);
  const riderMarkerRef = useRef<any>(null);
  const routeLineRef = useRef<any>(null);
  const stepsGroupRef = useRef<any[]>([]);
  const watchIdRef = useRef<number | null>(null);

  const routeCoordsRef = useRef<[number, number][]>([]);
  const totalDistanceRef = useRef(0);

  const [distanceLeft, setDistanceLeft] = useState<number | null>(null);
  const riderIcon = useCallback((isRider: boolean) =>
    L.divIcon({
      html: isRider
        ? `<div class="rider-bubble ${tipoVehiculo === 'Vehículo Eléctrico' ? 'rider-bubble--vehicle' : 'rider-bubble--bike'}"><i class="${tipoVehiculo === 'Vehículo Eléctrico' ? 'fas fa-motorcycle' : 'fas fa-bicycle'}"></i></div>`
        : `<div class="route-end-icon"><i class="fas fa-flag-checkered"></i></div>`,
      className: 'custom-map-icon',
      iconSize: [40, 40],
      iconAnchor: [20, 20]
    }), [tipoVehiculo]);

  const stopIcon = useCallback((number: number) =>
    L.divIcon({
      html: `<div class="stop-marker">${number}</div>`,
      className: 'custom-map-icon',
      iconSize: [26, 26],
      iconAnchor: [13, 13]
    }), []);

  const haversine = useCallback((lat1: number, lng1: number, lat2: number, lng2: number): number => {
    const R = 6371000;
    const toRad = (d: number) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }, []);

  const updateDistanceLeft = useCallback((cLat: number, cLng: number) => {
    const coords = routeCoordsRef.current;
    if (!coords.length) return;

    let minIndex = 0;
    let minDist = Infinity;

    for (let i = 0; i < coords.length; i++) {
      const d = haversine(cLat, cLng, coords[i][0], coords[i][1]);
      if (d < minDist) { minDist = d; minIndex = i; }
    }
    let walked = 0;
    for (let i = 0; i < minIndex; i++) {
      walked += haversine(coords[i][0], coords[i][1], coords[i + 1][0], coords[i + 1][1]);
    }

    const left = Math.max(0, (totalDistanceRef.current || 0) - walked);
    setDistanceLeft(left / 1000);
  }, [haversine]);

  const onPosition = useCallback((lat: number, lng: number) => {
    const map = mapInstanceRef.current;
    if (!map) return;
    if (riderMarkerRef.current) {
      riderMarkerRef.current.setLatLng([lat, lng]);
    }
    updateDistanceLeft(lat, lng);
  }, [updateDistanceLeft]);

  const startWatching = useCallback(() => {
    if (watchIdRef.current !== null) return;
    if (!navigator.geolocation) return;
    watchIdRef.current = navigator.geolocation.watchPosition(
      (pos) => onPosition(pos.coords.latitude, pos.coords.longitude),
      (err) => console.warn('GPS no disponible:', err.message),
      { enableHighAccuracy: true, maximumAge: 2000, timeout: 10000 }
    );
  }, [onPosition]);
  const fitToCoords = useCallback((map: any, coords: [number, number][], fallbackZoom = 14) => {
    if (!coords.length) {
      map.setView([MAP_CENTER.lat, MAP_CENTER.lng], fallbackZoom);
      return;
    }
    const latLngs = coords.map(([lat, lng]) => L.latLng(lat, lng));
    map.fitBounds(L.latLngBounds(latLngs), { padding: [50, 50], maxZoom: 16 });
  }, []);

  useEffect(() => {
    if (!mapContainerRef.current) return;
    if (!mapInstanceRef.current && typeof L !== 'undefined') {
      const map = L.map(mapContainerRef.current);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap | EcoRuta Concepción'
      }).addTo(map);

      mapInstanceRef.current = map;
      fitToCoords(map, allCoords);
    }
  }, [allCoords, fitToCoords]);
  useEffect(() => {
    if (!isActive) return;
    const map = mapInstanceRef.current;
    if (!map) return;
    const t = window.setTimeout(() => {
      map.invalidateSize();
    }, 60);
    return () => window.clearTimeout(t);
  }, [isActive]);
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || typeof L === 'undefined') return;
    stepsGroupRef.current.forEach((m) => m.remove());
    stepsGroupRef.current = [];
    if (routeLineRef.current) routeLineRef.current.remove();
    routeLineRef.current = null;
    if (riderMarkerRef.current) riderMarkerRef.current.remove();
    riderMarkerRef.current = null;
    routeCoordsRef.current = [];
    totalDistanceRef.current = 0;
    setDistanceLeft(null);

    if (!activePedido) {
      fitToCoords(map, allCoords);
      return;
    }

    const destLat = activePedido.destino_coords ? activePedido.destino_coords[0] : -23.412;
    const destLng = activePedido.destino_coords ? activePedido.destino_coords[1] : -57.441;
    const dest = L.latLng(destLat, destLng);

    // Origen real: comercio del pedido (coordenadas que cargó el comercio en administración)
    const startLat = activePedido.origen_coords ? activePedido.origen_coords[0] : -23.4004;
    const startLng = activePedido.origen_coords ? activePedido.origen_coords[1] : -57.433;

    // Llamada OSRM con pasos e instrucciones (geometría geojson)
    const url = `https://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${destLng},${destLat}?overview=full&geometries=geojson&steps=true&annotations=distance`;

    fetch(url)
      .then((res) => res.ok ? res.json() : Promise.reject('OSRM no disponible'))
      .then((data) => {
        const route = data?.routes?.[0];
        if (!route?.geometry?.coordinates || !Array.isArray(route.geometry.coordinates)) return;

        // Geometría [lng, lat] -> [lat, lng]
        const latLngs = (route.geometry.coordinates as [number, number][]).map(([lng, lat]) => [lat, lng] as [number, number]);
        routeCoordsRef.current = latLngs;
        totalDistanceRef.current = route.distance || 0;

        routeLineRef.current = L.polyline(latLngs, { color: '#059669', weight: 5, opacity: 0.85 }).addTo(map);

        // Marcador de destino
        L.marker(dest, { icon: new (L.divIcon)({ html: '<div class="route-end-icon"><i class="fas fa-flag-checkered"></i></div>', className: 'custom-map-icon', iconSize: [32, 32], iconAnchor: [16, 16] }) }).addTo(map);

        // Marcador del repartidor (inicia en el comercio/origen)
        riderMarkerRef.current = L.marker([startLat, startLng], { icon: riderIcon(true) }).addTo(map);
        map.setView([startLat, startLng], 15);

        // Puntos de giro de la ruta (referencia visual para el repartidor)
        const steps = (route.legs?.[0]?.steps || []) as any[];
        stepsGroupRef.current = steps
          .map((s) => {
            const c = s?.maneuver?.location;
            return c ? L.circleMarker([c[1], c[0]], { radius: 5, color: '#bd6a49', fillOpacity: 0.8 }) : null;
          })
          .filter(Boolean)
          .map((m) => m.addTo(map));

        map.fitBounds(L.latLngBounds([L.latLng(startLat, startLng), dest]), { padding: [50, 50], maxZoom: 16 });
      })
      .catch((err) => console.error('No se pudo trazar la ruta OSRM:', err));

    // Stops de todos los pedidos asignados (los mismos que ve el comercio/administrador)
    const otrosStops = assignedOrders.filter((o) => o.id_pedido !== activePedido.id_pedido && [1, 2, 3].includes(o.id_estado));
    otrosStops.forEach((o, i) => {
      const c = o.destino_coords;
      if (!c) return;
      const m = L.marker([c[0], c[1]], { icon: stopIcon(i + 1) }).addTo(map);
      m.bindTooltip(`Parada ${i + 1}: Pedido #${o.id_pedido}`, { direction: 'top', offset: L.point(0, -14) });
      stepsGroupRef.current.push(m);
    });

    // Iniciar GPS
    startWatching();
  }, [activePedido, assignedOrders, allCoords, riderIcon, stopIcon, startWatching, fitToCoords]);

  // Limpiar el watcher de GPS al desmontar
  useEffect(() => {
    return () => {
      if (watchIdRef.current !== null) {
        navigator.geolocation.clearWatch(watchIdRef.current);
        watchIdRef.current = null;
      }
    };
  }, []);

  return (
    <div className="repartidor-map-wrapper">
      <div className="map-toolbar">
        <div className="rider-progress">
          <span className="rider-progress__label">
            <i className="fas fa-route" /> Distancia restante
          </span>
          <strong className="rider-progress__value">
            {distanceLeft !== null ? `${distanceLeft.toFixed(2)} km` : '—'}
          </strong>
        </div>
      </div>
      <div ref={mapContainerRef} className="map-view-container" style={{ height: '420px', width: '100%', borderRadius: '12px' }} />
    </div>
  );
};

export default RepartidorMap;