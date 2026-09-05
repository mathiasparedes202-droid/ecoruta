import { FaBan, FaBicycle, FaCheck, FaMapMarkerAlt, FaQrcode, FaRoute, FaStopwatch, FaStore } from 'react-icons/fa';
import { Pedido } from '../../types/repartidor';

interface Props {
  order: Pedido;
  selected: boolean;
  index: number;
  isNextStop: boolean;
  canAccept: boolean;
  onSelect: (order: Pedido) => void;
  onStatusChange: (order: Pedido, status: number) => void;
  onConfirmDelivery: (order: Pedido) => void;
  onCancel?: (order: Pedido) => void;
}

const state = (id: number) =>
  id === 4 ? 'Entregado' : id === 3 ? 'En camino' : id === 2 ? 'Listo para salir' : id === 5 ? 'Cancelado' : 'Pendiente';

const OrderCard = ({
  order,
  selected,
  index,
  isNextStop,
  canAccept,
  onSelect,
  onStatusChange,
  onConfirmDelivery,
  onCancel,
}: Props) => (
  <article
    className={`delivery-card ${selected ? 'selected' : ''} ${isNextStop ? 'next-stop' : ''}`}
    onClick={() => onSelect(order)}
    onKeyDown={(e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        onSelect(order);
      }
    }}
    tabIndex={0} 
    role="button" 
  >
    <div className="delivery-card-top">
      <span className={`order-state state-${order.id_estado}`}>
        {state(order.id_estado)}
      </span>
      <span className="order-number">
        {isNextStop ? <FaRoute className="next-stop-icon" /> : null}
        Pedido #{order.id_pedido}
      </span>
    </div>
    <div className="stop-order">
      <span className="stop-order__badge">{index + 1}</span>
      <h3>{order.detalle_paquete || 'Pedido sin detalle'}</h3>
      {isNextStop && <span className="stop-order__next">Próxima parada</span>}
    </div>
    <p className="merchant-name">{order.razon_social || 'Comercio EcoRuta'}</p>
    {order.metodo_pago && (
      <span
        className={`payment-badge ${order.pagado ? 'payment-badge--paid' : order.metodo_pago === 'transferencia' ? 'payment-badge--pending' : 'payment-badge--cash'}`}
        title={order.pagado ? 'Este pedido ya está pagado. No cobres al entregar.' : order.metodo_pago === 'transferencia' ? 'La transferencia aún no fue confirmada por el comercio.' : 'Cobrá en efectivo al entregar el paquete.'}
      >
        {order.pagado
          ? order.metodo_pago === 'transferencia' ? 'Pagado por transferencia' : 'Efectivo ya cobrado'
          : order.metodo_pago === 'transferencia' ? 'Transferencia pendiente de confirmar' : `Cobrar ₲${(order.tarifa_ecologica || 0).toLocaleString('es-PY')} al entregar`}
      </span>
    )}
    <div className="stops">
      <p>
        <FaStore />
        <span>
          <small>RETIRO</small>
          {order.direccion_origen}
        </span>
      </p>
      <i className="stop-line" />
      <p>
        <FaMapMarkerAlt />
        <span>
          <small>ENTREGA</small>
          {order.direccion_destino}
        </span>
      </p>
    </div>
    <div className="delivery-meta">
      <span>{order.distancia_km?.toFixed(1)} km</span>
      <span>{order.peso_kg} kg</span>
      <span>{(order.tarifa_ecologica || 0).toLocaleString('es-PY')} ₲</span>
    </div>
    <div className="delivery-actions" onClick={(event) => event.stopPropagation()}>
      {order.id_estado === 1 && canAccept && (
        <button
          className="primary-action"
          onClick={() => onStatusChange(order, 2)}
        >
          <FaCheck /> Aceptar orden
        </button>
      )}
      {order.id_estado === 1 && !canAccept && (
        <span className="locked-action">
          <FaStopwatch /> Iniciá tu turno para aceptar
        </span>
      )}
      {order.id_estado === 2 && canAccept && (
        <button
          className="primary-action"
          onClick={() => onStatusChange(order, 3)}
        >
          <FaBicycle /> Iniciar recorrido
        </button>
      )}
      {order.id_estado === 2 && !canAccept && (
        <span className="locked-action">
          <FaStopwatch /> Iniciá tu turno para salir
        </span>
      )}
      {order.id_estado === 3 && (
        <button
          className="delivery-action"
          onClick={() => onConfirmDelivery(order)}
        >
          <FaQrcode /> Confirmar entrega
        </button>
      )}
      {order.id_estado === 4 && (
        <span className="completed-action">
          <FaCheck /> Entrega confirmada
        </span>
      )}
      {order.id_estado === 5 && (
        <span className="completed-action completed-action--cancelled">
          <FaBan /> Pedido cancelado
        </span>
      )}
      {onCancel && [1, 2, 3].includes(order.id_estado) && (
        <button
          type="button"
          className="cancel-action"
          onClick={() => onCancel(order)}
        >
          <FaBan /> Cancelar pedido
        </button>
      )}
    </div>
  </article>
);

export default OrderCard;
