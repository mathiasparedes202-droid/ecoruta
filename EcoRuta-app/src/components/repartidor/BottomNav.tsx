import { FaBoxOpen, FaHome, FaMapMarkerAlt, FaUser } from 'react-icons/fa';

export type TabKey = 'inicio' | 'ordenes' | 'mapa' | 'perfil';

interface Props {
  active: TabKey;
  onChange: (tab: TabKey) => void;
  pendingCount: number;
}

const ITEMS: { key: TabKey; label: string; icon: React.ReactNode }[] = [
  { key: 'inicio', label: 'Inicio', icon: <FaHome /> },
  { key: 'ordenes', label: 'Órdenes', icon: <FaBoxOpen /> },
  { key: 'mapa', label: 'Mapa', icon: <FaMapMarkerAlt /> },
  { key: 'perfil', label: 'Perfil', icon: <FaUser /> },
];

const BottomNav = ({ active, onChange, pendingCount }: Props) => (
  <nav className="bottom-nav" aria-label="Navegación principal">
    {ITEMS.map(({ key, label, icon }) => {
      const isActive = active === key;
      return (
        <button
          key={key}
          type="button"
          className={`bottom-nav__item ${isActive ? 'is-active' : ''}`}
          onClick={() => onChange(key)}
          aria-current={isActive ? 'page' : undefined}
        >
          <span className="bottom-nav__icon">
            {icon}
            {key === 'ordenes' && pendingCount > 0 && (
              <span className="bottom-nav__badge">{pendingCount}</span>
            )}
          </span>
          <span className="bottom-nav__label">{label}</span>
        </button>
      );
    })}
  </nav>
);

export default BottomNav;