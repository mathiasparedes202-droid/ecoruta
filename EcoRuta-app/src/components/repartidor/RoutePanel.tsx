import { FaMapMarkerAlt, FaQrcode, FaRoute } from 'react-icons/fa';
import RepartidorMap from '../../RepartidorMap';
import { Pedido } from '../../types/repartidor';

interface Props {
  order: Pedido | null;
  assignedOrders?: Pedido[];
  onConfirmDelivery: (order: Pedido) => void;
  tipoVehiculo?: 'Bicicleta' | 'Vehículo Eléctrico';
  showHeading?: boolean;
  isActive?: boolean;
  allCoords?: [number, number][];
}

const RoutePanel = ({ order, assignedOrders = [], onConfirmDelivery, tipoVehiculo = 'Bicicleta', showHeading = true, isActive = true, allCoords = [] }: Props) => (
  <aside className="route-panel">
    {showHeading && (
      <div className="panel-heading">
        <div>
          <p className="eyebrow">RUTA ACTUAL</p>
          <h2>{order ? `Pedido #${order.id_pedido}` : 'Elige una orden'}</h2>
        </div>
        <FaRoute className="route-heading-icon" />
      </div>
    )}
    {order ? (
      <>
        <div className="route-details">
          <p>
            <span>Destino</span>
            <strong>
              <FaMapMarkerAlt /> {order.direccion_destino}
            </strong>
          </p>
          <div>
            <span>{order.distancia_km?.toFixed(1)} km estimados</span>
            <span>{order.co2_ahorrado_kg} kg CO₂ evitados</span>
          </div>
        </div>
        <RepartidorMap activePedido={order} assignedOrders={assignedOrders} tipoVehiculo={tipoVehiculo} isActive={isActive} allCoords={allCoords} onConfirmDelivery={undefined} />
        {order.id_estado === 3 && (
          <button
            className="confirm-route-button"
            onClick={() => onConfirmDelivery(order)}
          >
            <FaQrcode /> Llegué al destino
          </button>
        )}
      </>
    ) : (
      <div className="route-placeholder">
        <FaRoute />
        <p>Selecciona una orden para ver el trayecto, el comercio y el destino.</p>
      </div>
    )}
  </aside>
);

export default RoutePanel;
