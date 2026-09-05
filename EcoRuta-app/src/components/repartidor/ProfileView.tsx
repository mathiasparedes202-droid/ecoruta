import {
  FaBicycle,
  FaIdCard,
  FaLeaf,
  FaMotorcycle,
  FaPowerOff,
  FaSyncAlt,
  FaTachometerAlt,
} from 'react-icons/fa';
import { RepartidorUser } from '../../types/repartidor';
import TurnControl, { TurnoState } from './TurnControl';

interface Props {
  user: RepartidorUser;
  turno: TurnoState;
  isRefreshing: boolean;
  onAvailabilityChange: (next: TurnoState) => void;
  onRefresh: () => void;
  onLogout: () => void;
}

const ProfileView = ({
  user,
  turno,
  isRefreshing,
  onAvailabilityChange,
  onRefresh,
  onLogout,
}: Props) => {
  const isVehicle = user.tipo_vehiculo === 'Vehículo Eléctrico';
  const stats = [
    { label: 'Matrícula', value: user.matricula || 'Sin asignar', icon: <FaTachometerAlt /> },
    { label: 'Rol', value: user.rol, icon: <FaIdCard /> },
    { label: 'Vehiculo', value: user.tipo_vehiculo, icon: isVehicle ? <FaMotorcycle /> : <FaBicycle /> },
    { label: 'Método Eco', value: '0g CO₂ en pedidos', icon: <FaLeaf /> },
  ];

  return (
    <div className="profile-view">
      <section className="profile-hero">
        <span className="profile-avatar">
          {isVehicle ? <FaMotorcycle /> : <FaBicycle />}
        </span>
        <div className="profile-hero__meta">
          <p className="eyebrow">REPARTIDOR</p>
          <h2>{user.displayName || user.email}</h2>
          <span className="profile-hero__email">{user.email}</span>
        </div>
      </section>

      <TurnControl turno={turno} onChange={onAvailabilityChange} />

      <section className="profile-card">
        <h3 className="profile-card__title">Datos del turno</h3>
        <div className="profile-stats">
          {stats.map(({ label, value, icon }) => (
            <div className="profile-stat" key={label}>
              <span className="profile-stat__icon">{icon}</span>
              <div>
                <small>{label}</small>
                <strong>{value}</strong>
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="profile-card">
        <h3 className="profile-card__title">Acciones</h3>
        <button className="profile-action" onClick={onRefresh} disabled={isRefreshing}>
          <FaSyncAlt className={isRefreshing ? 'spin' : ''} />
          {isRefreshing ? 'Actualizando…' : 'Actualizar órdenes'}
        </button>
        <button className="profile-action profile-action--danger" onClick={onLogout}>
          <FaPowerOff />
          Cerrar sesión
        </button>
      </section>
    </div>
  );
};

export default ProfileView;