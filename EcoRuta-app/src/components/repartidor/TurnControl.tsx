import { FaPause, FaPlay, FaStop } from 'react-icons/fa';

export type TurnoState = 'inactivo' | 'activo' | 'pausado';

interface Props {
  turno: TurnoState;
  onChange: (next: TurnoState) => void;
}

const info: Record<TurnoState, { label: string; desc: string; }> = {
  inactivo: { label: 'Turno sin iniciar', desc: 'Iniciá tu turno para poder aceptar pedidos.' },
  activo: { label: 'Turno activo', desc: 'Estás disponible para aceptar y entregar pedidos.' },
  pausado: { label: 'Turno en pausa', desc: 'No aceptás pedidos. Reanudá para seguir repartiendo.' },
};

const TurnControl = ({ turno, onChange }: Props) => {
  const isActive = turno === 'activo';
  const isInactive = turno === 'inactivo';

  return (
    <section className={`turn-control turn-control--${turno}`}>
      <div className="turn-control__status">
        <span className={isActive ? 'availability-dot' : 'availability-dot paused'} />
        <div>
          <strong>{info[turno].label}</strong>
          <small>{info[turno].desc}</small>
        </div>
      </div>
      <div className="turn-control__actions">
        {isInactive ? (
          <button className="turn-control__btn --start" onClick={() => onChange('activo')}>
            <FaPlay /> Iniciar turno
          </button>
        ) : isActive ? (
          <>
            <button className="turn-control__btn --pause" onClick={() => onChange('pausado')}>
              <FaPause /> Pausar turno
            </button>
            <button className="turn-control__btn --stop" onClick={() => onChange('inactivo')}>
              <FaStop /> Finalizar turno
            </button>
          </>
        ) : (
          <>
            <button className="turn-control__btn --resume" onClick={() => onChange('activo')}>
              <FaPlay /> Reanudar turno
            </button>
            <button className="turn-control__btn --stop" onClick={() => onChange('inactivo')}>
              <FaStop /> Finalizar turno
            </button>
          </>
        )}
      </div>
    </section>
  );
};

export default TurnControl;