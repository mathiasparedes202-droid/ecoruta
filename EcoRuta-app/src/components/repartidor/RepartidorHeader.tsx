import { FaBicycle, FaPowerOff, FaSyncAlt } from 'react-icons/fa';
import { RepartidorUser } from '../../types/repartidor';
import { TurnoState } from './TurnControl';
import NotificationsBell from './NotificationsBell';

interface Props { user: RepartidorUser; isAvailable: boolean; turno: TurnoState; isRefreshing: boolean; onAvailabilityChange: () => void; onRefresh: () => void; onLogout: () => void; onOpenOrders?: () => void; }

const statusLabel: Record<TurnoState, string> = {
  inactivo: 'Turno sin iniciar',
  activo: 'Disponible',
  pausado: 'En pausa',
};

const RepartidorHeader = ({ user, isAvailable, turno, isRefreshing, onAvailabilityChange, onRefresh, onLogout, onOpenOrders }: Props) => (
  <header className="courier-header">
    <div className="courier-brand">
      <span className="brand-mark">

      </span>
      <div>
        <strong>EcoRuta</strong>
        <small>Panel del repartidor</small>
      </div>
    </div>
    <div className="courier-header-actions">
      <NotificationsBell onNewOrder={onOpenOrders} />
      <button className="availability-button" onClick={onAvailabilityChange}>
        <span className={isAvailable ? 'availability-dot' : 'availability-dot paused'} />
        {statusLabel[turno]}
      </button>
      <button className="header-icon-button" onClick={onRefresh} aria-label="Actualizar órdenes" title="Actualizar órdenes">
        <FaSyncAlt className={isRefreshing ? 'spin' : ''} />
      </button>
      <div className="courier-profile">
        <span className="profile-icon">
          <FaBicycle />
        </span>
        <span>
          <strong>{user.displayName || user.email}</strong>
          <small>{user.matricula || 'Sin matrícula registrada'}</small>
        </span>
      </div>
      <button className="header-icon-button logout" onClick={onLogout} aria-label="Cerrar sesión" title="Cerrar sesión">
        <FaPowerOff />
      </button>
    </div>
  </header>
);

export default RepartidorHeader;
