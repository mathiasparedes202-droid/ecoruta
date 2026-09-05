import {
  FaBicycle,
  FaCheckCircle,
  FaLeaf,
  FaMapMarkedAlt,
  FaRoute,
  FaWalking,
} from 'react-icons/fa';

interface Props {
  pending: number;
  enRoute: number;
  deliveries: number;
  totalStops: number;
  distance: number;
  co2: number;
  earnings: number;
}

const CourierStats = ({
  pending,
  enRoute,
  deliveries,
  totalStops,
  distance,
  co2,
  earnings,
}: Props) => {
  const progress = totalStops > 0 ? Math.min(100, Math.round((deliveries / totalStops) * 100)) : 0;

  return (
    <section className="stats-view" aria-label="Métricas del repartidor">
      <div className="stats-hero">
        <div className="stats-hero__score">
          <strong>{progress}%</strong>
          <span>del turno</span>
        </div>
        <div className="stats-hero__bar">
          <i style={{ width: `${progress}%` }} />
        </div>
        <p>{deliveries} de {totalStops} paradas completadas</p>
      </div>

      <div className="stats-grid">
        <div className="stat-card">
          <span className="stat-card__icon amber"><FaWalking /></span>
          <div>
            <strong>{pending}</strong>
            <small>por preparar</small>
          </div>
        </div>
        <div className="stat-card">
          <span className="stat-card__icon violet"><FaRoute /></span>
          <div>
            <strong>{enRoute}</strong>
            <small>en camino</small>
          </div>
        </div>
        <div className="stat-card">
          <span className="stat-card__icon green"><FaCheckCircle /></span>
          <div>
            <strong>{deliveries}</strong>
            <small>entregas</small>
          </div>
        </div>
        <div className="stat-card">
          <span className="stat-card__icon blue"><FaMapMarkedAlt /></span>
          <div>
            <strong>{distance.toFixed(1)} km</strong>
            <small>recorridos</small>
          </div>
        </div>
      </div>

      <div className="stats-impact">
        <div className="stats-impact__item">
          <span className="stat-card__icon green"><FaLeaf /></span>
          <div>
            <strong>{co2.toFixed(1)} kg</strong>
            <small>CO₂ ahorrado</small>
          </div>
        </div>
        <div className="stats-impact__item">
          <span className="stat-card__icon amber"><FaBicycle /></span>
          <div>
            <strong>{earnings.toLocaleString('es-PY')} ₲</strong>
            <small>ingresos del turno</small>
          </div>
        </div>
      </div>
    </section>
  );
};

export default CourierStats;