import { useCallback, useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import { API } from './config';
import BottomNav, { TabKey } from './components/repartidor/BottomNav';
import DeliveryConfirmationModal from './components/repartidor/DeliveryConfirmationModal';
import CancelOrderModal from './components/repartidor/CancelOrderModal';
import OrderList from './components/repartidor/OrderList';
import ProfileView from './components/repartidor/ProfileView';
import RepartidorHeader from './components/repartidor/RepartidorHeader';
import RoutePanel from './components/repartidor/RoutePanel';
import CourierStats from './components/repartidor/CourierStats';
import { Pedido, RepartidorUser } from './types/repartidor';

interface RepartidorDashboardProps { user: RepartidorUser; onLogout: () => void; }

type TurnoState = 'inactivo' | 'activo' | 'pausado';

const TURNO_FEEDBACK: Record<TurnoState, string> = {
  activo: 'Tu turno está activo. Ya podés recibir pedidos asignados.',
  pausado: 'Turno en pausa. No vas a recibir nuevos pedidos.',
  inactivo: 'Turno finalizado. Inicialo de nuevo para repartir.',
};

const rawNum = (value: unknown): number => {
  const n = Number(value);
  return isFinite(n) && n !== 0 ? n : NaN;
};

const normalizeOrder = (order: Pedido, index: number): Pedido => {
  const anyOrder = order as Pedido & { origen_lat?: unknown; origen_lng?: unknown; dest_lat?: unknown; dest_lng?: unknown };
  const origenLat = rawNum(anyOrder.origen_lat);
  const origenLng = rawNum(anyOrder.origen_lng);
  const destLat = rawNum(anyOrder.dest_lat);
  const destLng = rawNum(anyOrder.dest_lng);
  return {
    ...order,
    id_pedido: Number(order.id_pedido), id_estado: Number(order.id_estado),
    distancia_km: order.distancia_km ? Number(order.distancia_km) : 2.5,
    peso_kg: order.peso_kg ? Number(order.peso_kg) : 1.5,
    tarifa_ecologica: order.tarifa_ecologica ? Number(order.tarifa_ecologica) : 15000,
    co2_ahorrado_kg: order.co2_ahorrado_kg ? Number(order.co2_ahorrado_kg) : 0.36,
    metodo_pago: anyOrder.metodo_pago === 'transferencia' ? 'transferencia' : 'efectivo',
    pagado: Number(anyOrder.pagado ?? 0) === 1,
    origen_coords: order.origen_coords
      || (isFinite(origenLat) && isFinite(origenLng) ? [origenLat, origenLng] : undefined)
      || [-23.4004 + (index % 4) * 0.004, -57.433 - (index % 4) * 0.003],
    destino_coords: order.destino_coords
      || (isFinite(destLat) && isFinite(destLng) ? [destLat, destLng] : undefined)
      || [-23.412 + (index % 3) * 0.005, -57.441 - (index % 3) * 0.002]
  };
};

const RepartidorDashboard = ({ user, onLogout }: RepartidorDashboardProps) => {
  const repartidorId = Number(user.id_repartidor);

  const [orders, setOrders] = useState<Pedido[]>([]);
  const [selectedOrder, setSelectedOrder] = useState<Pedido | null>(null);
  const [deliveryOrder, setDeliveryOrder] = useState<Pedido | null>(null);
  const [cancellingOrder, setCancellingOrder] = useState<Pedido | null>(null);
  const [filter, setFilter] = useState('activos');
  const [activeTab, setActiveTab] = useState<TabKey>('inicio');
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [turno, setTurno] = useState<TurnoState>(user.disponible ? 'activo' : 'inactivo');
  const [turnoError, setTurnoError] = useState('');
  const isAvailable = turno === 'activo';

  const authHeaders = (json = false): Record<string, string> => {
    const headers: Record<string, string> = {};
    if (json) headers['Content-Type'] = 'application/json';
    const token = localStorage.getItem('ecoruta_token');
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  };

  const syncTurno = useCallback(async () => {
    try {
      const response = await fetch(`${API}/turno/actual`, { credentials: 'include', headers: authHeaders() });
      if (!response.ok) return;
      const data = await response.json();
      const estado = data?.turno?.estado;
      setTurno(estado === 'activo' ? 'activo' : estado === 'pausado' ? 'pausado' : 'inactivo');
      setTurnoError('');
    } catch {
      setTurnoError('No pudimos ver tu turno. Revisá tu conexión y volvé a intentar.');
    }
  }, []);

  useEffect(() => { syncTurno(); }, [syncTurno]);

  const applyTurnoChange = async (next: TurnoState) => {
    const action = next === 'inactivo' ? 'finish' : next === 'pausado' ? 'pause' : turno === 'inactivo' ? 'start' : 'resume';
    const method = action === 'start' ? 'POST' : 'PATCH';
    try {
      const response = await fetch(`${API}/turno/${action}`, {
        method, credentials: 'include', headers: authHeaders(true),
      });
      if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        throw new Error(body.message || 'No pudimos actualizar el turno.');
      }
      await syncTurno();
      await loadOrders();
      Swal.fire({ toast: true, position: 'top-end', icon: next === 'activo' ? 'success' : 'info', title: TURNO_FEEDBACK[next], showConfirmButton: false, timer: 2200 });
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Turno no actualizado', text: error instanceof Error ? error.message : 'Intentalo nuevamente.' });
    }
  };

  const handleTurnoChange = (next: TurnoState) => { void applyTurnoChange(next); };

  const loadOrders = useCallback(async (showFeedback = false) => {
    setIsRefreshing(true);
    try {
      const token = localStorage.getItem('ecoruta_token');
      const query = repartidorId ? `?repartidor_id=${repartidorId}` : '';
      const response = await fetch(`${API}/orders${query}`, { credentials: 'include', headers: token ? { Authorization: `Bearer ${token}` } : {} });
      if (!response.ok) throw new Error(`No pudimos actualizar las órdenes (${response.status}).`);
      const data: Pedido[] = await response.json();
      const formattedOrders = Array.isArray(data) ? data.map(normalizeOrder) : [];
      setOrders(formattedOrders);
      setSelectedOrder(current => formattedOrders.find(order => order.id_pedido === current?.id_pedido) || formattedOrders.find(order => order.id_estado === 3) || formattedOrders.find(order => order.id_estado === 2) || formattedOrders[0] || null);
      if (showFeedback) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tu turno está actualizado', showConfirmButton: false, timer: 1800 });
    } catch (error) {
      console.error('No fue posible cargar las órdenes:', error);
      if (showFeedback) Swal.fire({ icon: 'warning', title: 'Sin conexión', text: 'No pudimos actualizar tus órdenes. Cuando vuelva la conexión, se actualizan solas.' });
    } finally { setIsLoading(false); setIsRefreshing(false); }
  }, [repartidorId]);

  useEffect(() => { loadOrders(); }, [loadOrders]);

  useEffect(() => {
    const interval = setInterval(() => loadOrders(), 600000);
    const onFocus = () => loadOrders();
    window.addEventListener('focus', onFocus);
    document.addEventListener('visibilitychange', onFocus);
    return () => {
      clearInterval(interval);
      window.removeEventListener('focus', onFocus);
      document.removeEventListener('visibilitychange', onFocus);
    };
  }, [loadOrders]);

  const updateOrderStatus = async (order: Pedido, status: number, note?: string) => {
    const token = localStorage.getItem('ecoruta_token');
    const response = await fetch(`${API}/orders/${order.id_pedido}/status`, {
      method: 'PATCH', credentials: 'include',
      headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ id_estado: status, id_usuario_cambio: user.id_usuario, observacion: note || (status === 3 ? 'El repartidor inició el recorrido.' : 'Estado actualizado por el repartidor.') })
    });
    if (!response.ok) { const body = await response.json().catch(() => ({})); throw new Error(body.message || 'No pudimos guardar el cambio.'); }
    await loadOrders();
  };

  const handleStatusChange = async (order: Pedido, status: number) => {
    if (turno !== 'activo' && (status === 2 || status === 3)) {
      Swal.fire({ icon: 'warning', title: 'Tu turno no está activo', text: 'Inicia tu turno antes de aceptar o iniciar el recorrido de un pedido.', confirmButtonColor: '#176b52' });
      return;
    }
    try {
      await updateOrderStatus(order, status);
      if (status === 3) {
        setSelectedOrder(order);
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '¡Ya estás en camino!', text: 'La ruta quedó lista para tu recorrido.', showConfirmButton: false, timer: 2200 });
      }
    } catch (error) { Swal.fire({ icon: 'error', title: 'No pudimos guardar el cambio', text: error instanceof Error ? error.message : 'Vuelve a intentarlo.' }); }
  };
  const markPaid = async (order: Pedido, pagado: boolean, extra: Record<string, unknown> = {}) => {
    const token = localStorage.getItem('ecoruta_token');
    const response = await fetch(`${API}/orders/${order.id_pedido}/pago`, {
      method: 'PATCH', credentials: 'include',
      headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ pagado, ...extra })
    });
    if (!response.ok) { const body = await response.json().catch(() => ({})); throw new Error(body.message || 'No pudimos registrar el pago.'); }
    await loadOrders();
  };

  const handleDelivery = async (order: Pedido, note: string, cash: { montoRecibido?: number } = {}) => {
    try {
      await updateOrderStatus(order, 4, note);
      if (!order.pagado && (order.metodo_pago === 'efectivo' || order.metodo_pago === 'mixto') && !!cash.montoRecibido) {
        await markPaid(order, true, { monto_recibido: cash.montoRecibido });
      }
    } catch (error) {
      setDeliveryOrder(null);
      Swal.fire({ icon: 'error', title: 'No pudimos guardar la entrega', text: error instanceof Error ? error.message : 'Vuelve a intentarlo.' });
      return;
    }
    setDeliveryOrder(null);
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Entrega registrada', text: `El pedido #${order.id_pedido} ya figura como entregado.`, showConfirmButton: false, timer: 2200 });
  };

  const handleCancelOrder = async (order: Pedido, motivo: string) => {
    const token = localStorage.getItem('ecoruta_token');
    const response = await fetch(`${API}/orders/${order.id_pedido}/cancel`, {
      method: 'PATCH', credentials: 'include',
      headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ motivo, id_usuario_cambio: user.id_usuario })
    });
    if (!response.ok) { const body = await response.json().catch(() => ({})); throw new Error(body.message || 'No pudimos cancelar el pedido.'); }
    setCancellingOrder(null);
    await loadOrders();
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Pedido cancelado', text: `El pedido #${order.id_pedido} fue cancelado y te notificamos al administrador.`, showConfirmButton: false, timer: 2200 });
  };

  const handleSelectOrder = (order: Pedido) => {
    setSelectedOrder(order);
    setActiveTab('mapa');
  };

  const visibleOrders = useMemo(() => orders.filter(order => (filter === 'activos' ? [1, 2, 3].includes(order.id_estado) : filter === 'pendientes' ? [1, 2].includes(order.id_estado) : filter === 'en_camino' ? order.id_estado === 3 : order.id_estado === 4)), [filter, orders]);
  const summary = useMemo(() => ({
    pending: orders.filter(order => order.id_estado === 1 || order.id_estado === 2).length,
    enRoute: orders.filter(order => order.id_estado === 3).length,
    delivered: orders.filter(order => order.id_estado === 4),
    distance: orders.filter(order => order.id_estado === 4).reduce((total, order) => total + (order.distancia_km || 0), 0)
  }), [orders]);

  const pendingCount = summary.pending + summary.enRoute;

  const totalStops = orders.length;
  const co2Saved = orders.reduce((acc, o) => acc + (o.co2_ahorrado_kg || 0), 0);
  const provinceEarnings = summary.delivered.reduce((acc, o) => acc + (o.tarifa_ecologica || 0), 0);

  const allCoords = useMemo<[number, number][]>(() => {
    const list: [number, number][] = [];
    if (selectedOrder?.origen_coords) list.push(selectedOrder.origen_coords);
    if (selectedOrder?.destino_coords) list.push(selectedOrder.destino_coords);
    orders.forEach((o) => {
      if (o.origen_coords) list.push(o.origen_coords);
      if (o.destino_coords) list.push(o.destino_coords);
    });
    return list;
  }, [orders, selectedOrder]);

  const openOrdersTab = () => setActiveTab('ordenes');

  return <main className="courier-dashboard">
    <RepartidorHeader user={user} isAvailable={isAvailable} turno={turno} isRefreshing={isRefreshing} onAvailabilityChange={() => handleTurnoChange(turno === 'activo' ? 'pausado' : 'activo')} onRefresh={() => loadOrders(true)} onLogout={onLogout} onOpenOrders={openOrdersTab} />
    <div className="courier-content">
      {turnoError && (
        <div className="courier-warning-banner">
          <span>{turnoError}</span>
        </div>
      )}
      <div className="tab-view" data-tab={activeTab === 'inicio' ? 'true' : 'invisible'}>
        <section className="tab-header">
          <p className="eyebrow">TUS MÉTRICAS</p>
          <h1>Turno de hoy</h1>
        </section>
        <CourierStats
          pending={summary.pending}
          enRoute={summary.enRoute}
          deliveries={summary.delivered.length}
          totalStops={totalStops}
          distance={summary.distance}
          co2={co2Saved}
          earnings={provinceEarnings}
        />
      </div>

      <div className="tab-view" data-tab={activeTab === 'ordenes' ? 'true' : 'invisible'}>
        <OrderList orders={visibleOrders} totalOrders={orders} selectedOrderId={selectedOrder?.id_pedido} filter={filter} loading={isLoading} canAccept={isAvailable} onFilterChange={setFilter} onSelect={handleSelectOrder} onStatusChange={handleStatusChange} onConfirmDelivery={setDeliveryOrder} onCancel={setCancellingOrder} showHeading />
      </div>

      <div className="tab-view" data-tab={activeTab === 'mapa' ? 'true' : 'invisible'}>
        <section className="route-panel route-panel--full">
          <RoutePanel order={selectedOrder} assignedOrders={orders.filter(o => [1, 2, 3].includes(o.id_estado))} tipoVehiculo={user.tipo_vehiculo} isActive={activeTab === 'mapa'} allCoords={allCoords} onConfirmDelivery={setDeliveryOrder} showHeading />
        </section>
      </div>

      <div className="tab-view" data-tab={activeTab === 'perfil' ? 'true' : 'invisible'}>
        <ProfileView user={user} turno={turno} isRefreshing={isRefreshing} onAvailabilityChange={handleTurnoChange} onRefresh={() => loadOrders(true)} onLogout={onLogout} />
      </div>
    </div>
    <BottomNav active={activeTab} onChange={setActiveTab} pendingCount={pendingCount} />
    {deliveryOrder && <DeliveryConfirmationModal order={deliveryOrder} onClose={() => setDeliveryOrder(null)} onConfirm={handleDelivery} />}
    {cancellingOrder && <CancelOrderModal order={cancellingOrder} onClose={() => setCancellingOrder(null)} onConfirm={handleCancelOrder} />}
  </main>;
};

export default RepartidorDashboard;