import { FaBoxOpen, FaCheckCircle, FaClock, FaMapMarkedAlt, FaRoute } from 'react-icons/fa';
import { Pedido } from '../../types/repartidor';
import OrderCard from './OrderCard';

interface Props {
  orders: Pedido[];
  totalOrders: Pedido[];
  selectedOrderId?: number;
  filter: string;
  loading: boolean;
  showHeading?: boolean;
  canAccept?: boolean;
  onFilterChange: (filter: string) => void;
  onSelect: (order: Pedido) => void;
  onStatusChange: (order: Pedido, status: number) => void;
  onConfirmDelivery: (order: Pedido) => void;
  onCancel?: (order: Pedido) => void;
}

const OrderList = ({
  orders,
  totalOrders,
  selectedOrderId,
  filter,
  loading,
  showHeading = true,
  canAccept = true,
  onFilterChange,
  onSelect,
  onStatusChange,
  onConfirmDelivery,
  onCancel,
}: Props) => {
  const filters = [
    {
      id: 'activos',
      label: 'Activas',
      icon: FaBoxOpen,
      count: totalOrders.filter((o) => [1, 2, 3].includes(o.id_estado)).length,
    },
    {
      id: 'pendientes',
      label: 'Pendientes',
      icon: FaClock,
      count: totalOrders.filter((o) => [1, 2].includes(o.id_estado)).length,
    },
    {
      id: 'en_camino',
      label: 'En camino',
      icon: FaRoute,
      count: totalOrders.filter((o) => o.id_estado === 3).length,
    },
    {
      id: 'entregados',
      label: 'Entregadas',
      icon: FaCheckCircle,
      count: totalOrders.filter((o) => o.id_estado === 4).length,
    },
  ];

  const activeStops = totalOrders.filter((o) => o.id_estado !== 4);
  const nextStopIndex = activeStops.findIndex((o) => o.id_estado !== 3);
  const turnDistance = activeStops.reduce((acc, o) => acc + (o.distancia_km || 0), 0);

  return (
    <section className="orders-panel">
      {showHeading && (
        <div className="panel-heading">
          <div>
            <h2>Mi recorrido</h2>
            <p className="orders-subtitle">Organiza las paradas de tu turno</p>
          </div>
        </div>
      )}
      {activeStops.length > 0 && (
        <div className="turn-route-summary">
          <div>
            <strong>{activeStops.length}</strong>
            <small>paradas activas</small>
          </div>
          <div>
            <strong>{turnDistance.toFixed(1)} km</strong>
            <small>por recorrer</small>
          </div>
          <div>
            <strong>{activeStops.filter((o) => o.id_estado === 3).length}</strong>
            <small>en camino</small>
          </div>
        </div>
      )}
      <div className="order-filters">
        {filters.map(({ id, label, icon: Icon, count }) => (
          <button
            key={id}
            className={filter === id ? 'active' : ''}
            onClick={() => onFilterChange(id)}
          >
            <Icon /> {label} <b>{count}</b>
          </button>
        ))}
      </div>
      {loading ? (
        <div className="orders-empty">Cargando las órdenes de tu turno…</div>
      ) : orders.length ? (
        <div className="orders-list">
          {orders.map((order, index) => (
            <OrderCard
              key={order.id_pedido}
              order={order}
              index={index}
              isNextStop={nextStopIndex === index && order.id_estado !== 4}
              canAccept={canAccept}
              selected={selectedOrderId === order.id_pedido}
              onSelect={onSelect}
              onStatusChange={onStatusChange}
              onConfirmDelivery={onConfirmDelivery}
              onCancel={onCancel}
            />
          ))}
        </div>
      ) : (
        <div className="orders-empty">
          <FaMapMarkedAlt />
          <h3>No hay recorrido por organizar</h3>
          <p>Cuando tengas órdenes asignadas, aquí se ordenará tu ruta de reparto.</p>
        </div>
      )}
    </section>
  );
};

export default OrderList;
