import { useEffect, useRef, useState } from 'react';
import { AlertCircle, Bell, CheckCheck, PackagePlus, UserRound, XCircle } from 'lucide-react';
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead
} from '../services/adminService.js';

const TYPES = {
  nuevo_pedido: { icon: PackagePlus, tone: 'orange' },
  pedido_cancelado: { icon: XCircle, tone: 'red' },
  pedido_asignado: { icon: UserRound, tone: 'blue' },
  pedido_entregado: { icon: CheckCheck, tone: 'green' }
};

function timeAgo(fecha) {
  const date = new Date(String(fecha).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return fecha;
  const seconds = Math.round((Date.now() - date.getTime()) / 1000);
  if (seconds < 60) return 'ahora mismo';
  if (seconds < 3600) return `hace ${Math.floor(seconds / 60)} min`;
  if (seconds < 86400) return `hace ${Math.floor(seconds / 3600)} h`;
  return date.toLocaleDateString('es-PY', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export default function NotificationBell({ onNavigateToOrders }) {
  const [items, setItems] = useState([]);
  const [unread, setUnread] = useState(0);
  const [open, setOpen] = useState(false);
  const rootRef = useRef(null);

  const load = async () => {
    try {
      const data = await fetchNotifications();
      const list = Array.isArray(data) ? data : data.notificaciones || [];
      setItems(list);
      setUnread(Number(data?.no_leidas ?? list.filter((i) => !Number(i.leida)).length));
    } catch {
      /* el polling no debe romper la interfaz */
    }
  };

  useEffect(() => {
    load();
    const id = setInterval(load, 8000);
    return () => clearInterval(id);
  }, []);

  useEffect(() => {
    if (!open) return;
    const onClickOutside = (event) => {
      if (rootRef.current && !rootRef.current.contains(event.target)) setOpen(false);
    };
    document.addEventListener('mousedown', onClickOutside);
    return () => document.removeEventListener('mousedown', onClickOutside);
  }, [open]);

  const handleRead = async (id) => {
    try { await markNotificationRead(id); } catch { /* optimista: se refresca en el próximo poll */ }
    setItems((prev) => prev.map((n) => (n.id_notificacion === id ? { ...n, leida: 1 } : n)));
    setUnread((u) => Math.max(0, u - 1));
  };

  const handleAllRead = async () => {
    try { await markAllNotificationsRead(); } catch { /* igual refresca */ }
    const list = (await fetchNotifications().catch(() => null))?.notificaciones;
    if (list) setItems(list);
    setUnread(0);
  };

  const goToOrders = () => {
    setOpen(false);
    onNavigateToOrders?.();
  };

  return (
    <div className="admin-notif" ref={rootRef}>
      <button
        type="button"
        className={`admin-notif__bell${open ? ' open' : ''}`}
        onClick={() => setOpen((o) => !o)}
        aria-label={`Notificaciones${unread > 0 ? ` (${unread} sin leer)` : ''}`}
      >
        <Bell size={19} strokeWidth={1.9} />
        {unread > 0 && <span className="admin-notif__badge">{unread > 99 ? '99+' : unread}</span>}
      </button>

      {open && (
        <div className="admin-notif__panel">
          <header>
            <strong>Notificaciones</strong>
            <button type="button" onClick={handleAllRead} disabled={unread === 0}>
              <CheckCheck size={14} /> Marcar todas leídas
            </button>
          </header>
          <div className="admin-notif__list">
            {items.length === 0 ? (
              <p className="admin-notif__empty"><Bell size={18} /> Por ahora no hay novedades. Acá vas a ver los pedidos nuevos y las cancelaciones.</p>
            ) : (
              items.map((n) => {
                const meta = TYPES[n.tipo] || { icon: AlertCircle, tone: '' };
                const Icon = meta.icon;
                const isNew = !Number(n.leida);
                return (
                  <div
                    key={n.id_notificacion}
                    className={`admin-notif__item${isNew ? ' unread' : ''}`}
                    onClick={() => handleRead(n.id_notificacion)}
                  >
                    <span className={`admin-notif__ic admin-notif__ic--${meta.tone}`}><Icon size={15} /></span>
                    <div>
                      <strong>
                        {n.titulo}
                        {isNew && <i className="admin-notif__dot" />}
                      </strong>
                      <p>{n.mensaje}</p>
                      <small>{timeAgo(n.fecha)}</small>
                      {n.tipo === 'nuevo_pedido' && (
                        <button
                          type="button"
                          className="admin-notif__assign"
                          onClick={(e) => { e.stopPropagation(); goToOrders(); }}
                        >
                          Asignar repartidor
                        </button>
                      )}
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
}