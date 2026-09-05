import { useCallback, useEffect, useRef, useState } from 'react';
import { FaBell, FaCheckCircle, FaCheckDouble, FaMoneyBillWave, FaRocket, FaRoute, FaStore } from 'react-icons/fa';
import { API } from '../../config';
import { Notificacion } from '../../types/repartidor';

type Props = {
  onNewOrder?: () => void;
};

const TYPE_ICON: Record<string, typeof FaStore> = {
  nuevo_pedido: FaStore,
  pedido_cancelado: FaCheckCircle,
  pedido_asignado: FaRoute,
  pedido_pagado: FaMoneyBillWave,
  pedido_relanzado: FaRocket,
};

const RESPONSIVE_CLASS: Record<string, string> = {
  nuevo_pedido: '--new-order',
  pedido_cancelado: '--cancelled',
  pedido_asignado: '--assigned',
  pedido_pagado: '--paid',
  pedido_relanzado: '--relaunched',
};

const TOAST_MS = 6500;

const timeAgo = (fecha: string) => {
  const date = new Date(String(fecha).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '';
  const seconds = Math.round((Date.now() - date.getTime()) / 1000);
  if (seconds < 60) return 'ahora';
  if (seconds < 3600) return `hace ${Math.floor(seconds / 60)} min`;
  if (seconds < 86400) return `hace ${Math.floor(seconds / 3600)} h`;
  return date.toLocaleDateString('es-PY', { day: '2-digit', month: '2-digit' });
};

const NotificationsBell = ({ onNewOrder }: Props) => {
  const [items, setItems] = useState<Notificacion[]>([]);
  const [unread, setUnread] = useState(0);
  const [open, setOpen] = useState(false);
  const [toasts, setToasts] = useState<Notificacion[]>([]);
  const seenRef = useRef<Set<number>>(new Set());
  const loadedOnceRef = useRef(false);
  const rootRef = useRef<HTMLDivElement>(null);

  const authHeaders = (): Record<string, string> => {
    const headers: Record<string, string> = {};
    const token = localStorage.getItem('ecoruta_token');
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  };

  const dismissToast = (notificationId: number) =>
    setToasts((list) => list.filter((t) => t.id_notificacion !== notificationId));

  const pushToast = useCallback((notif: Notificacion) => {
    setToasts((list) => [notif, ...list].slice(0, 3));
    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
      try { navigator.vibrate(120); } catch { /* dispositivo sin soporte */ }
    }
  }, []);

  useEffect(() => {
    if (toasts.length === 0) return;
    const timers = toasts.map((t) => setTimeout(() => dismissToast(t.id_notificacion), TOAST_MS));
    return () => timers.forEach((timer) => clearTimeout(timer));
  }, [toasts]);

  const markRead = useCallback(async (notificationId: number) => {
    try {
      await fetch(`${API}/notificaciones/${notificationId}/leida`, {
        method: 'PATCH', credentials: 'include', headers: authHeaders(),
      });
    } catch { /* silencioso: el estado local ya se actualiza */ }
    setItems((prev) => prev.map((n) => (n.id_notificacion === notificationId ? { ...n, leida: 1 } : n)));
    setUnread((count) => Math.max(0, count - 1));
  }, []);

  const load = useCallback(async () => {
    try {
      const response = await fetch(`${API}/notificaciones`, { credentials: 'include', headers: authHeaders() });
      if (!response.ok) return;
      const data = await response.json();
      const list: Notificacion[] = Array.isArray(data) ? data : data.notificaciones || [];
      setItems(list);
      setUnread(Number(data?.no_leidas ?? list.filter((i) => !Number(i.leida)).length));

      if (loadedOnceRef.current) {
        const fresh = list.filter(
          (n) => !Number(n.leida) && !seenRef.current.has(n.id_notificacion)
        );
        fresh.forEach(pushToast);
      }
      list.forEach((n) => seenRef.current.add(n.id_notificacion));
      loadedOnceRef.current = true;
    } catch { /* silencioso */ }
  }, [pushToast]);

  useEffect(() => {
    load();
    const id = setInterval(load, 15000);
    const onFocus = () => load();
    window.addEventListener('focus', onFocus);
    document.addEventListener('visibilitychange', onFocus);
    return () => {
      clearInterval(id);
      window.removeEventListener('focus', onFocus);
      document.removeEventListener('visibilitychange', onFocus);
    };
  }, [load]);

  useEffect(() => {
    if (!open) return;
    const onClickOutside = (event: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onClickOutside);
    return () => document.removeEventListener('mousedown', onClickOutside);
  }, [open]);

  const markAllRead = async () => {
    try {
      await fetch(`${API}/notificaciones/leer-todas`, {
        method: 'POST', credentials: 'include', headers: authHeaders(),
      });
    } catch { /* igual refresca */ }
    setToasts([]);
    setItems((prev) => prev.map((n) => ({ ...n, leida: 1 })));
    setUnread(0);
  };

  const jumpToOrder = (notif: Notificacion) => {
    if (!Number(notif.leida)) markRead(notif.id_notificacion);
    dismissToast(notif.id_notificacion);
    setOpen(false);
    if (onNewOrder && notif.id_pedido) onNewOrder();
  };

  return (
    <div className="notif-bell" ref={rootRef}>
      <button
        type="button"
        className={`notif-bell__btn${open ? ' open' : ''}`}
        onClick={() => setOpen((isOpen) => !isOpen)}
        aria-label={`Notificaciones${unread > 0 ? ` (${unread} sin leer)` : ''}`}
      >
        <FaBell />
        {unread > 0 && <span className="notif-bell__badge">{unread > 9 ? '9+' : unread}</span>}
      </button>

      {open && (
        <div className="notif-bell__panel">
          <header>
            <strong>Notificaciones</strong>
            <button type="button" onClick={markAllRead} disabled={unread === 0}>
              <FaCheckDouble /> Marcar todas
            </button>
          </header>
          <div className="notif-bell__list">
            {items.length === 0 ? (
              <p className="notif-bell__empty">
                Por ahora no hay novedades. Acá te avisamos cuando te asignen un pedido o confirmen un pago.
              </p>
            ) : (
              items.map((n) => {
                const Icon = TYPE_ICON[n.tipo] || FaBell;
                const cls = RESPONSIVE_CLASS[n.tipo] || '';
                return (
                  <button
                    type="button"
                    key={n.id_notificacion}
                    className={`notif-bell__item${!Number(n.leida) ? ' unread' : ''}`}
                    onClick={() => jumpToOrder(n)}
                  >
                    <span className={`notif-bell__ic${cls}`}><Icon /></span>
                    <span className="notif-bell__body">
                      <strong>{n.titulo}</strong>
                      <span>{n.mensaje}</span>
                      <small>{timeAgo(n.fecha)}</small>
                    </span>
                  </button>
                );
              })
            )}
          </div>
        </div>
      )}

      {toasts.length > 0 && (
        <div className="notif-toasts" role="status" aria-live="polite">
          {toasts.map((n) => {
            const Icon = TYPE_ICON[n.tipo] || FaBell;
            const cls = RESPONSIVE_CLASS[n.tipo] || '';
            return (
              <div
                key={n.id_notificacion}
                className={`notif-toast${cls}`}
                onClick={() => jumpToOrder(n)}
              >
                <span className={`notif-bell__ic${cls}`}><Icon /></span>
                <span className="notif-toast__body">
                  <strong>{n.titulo}</strong>
                  <span>{n.mensaje}</span>
                </span>
                <button
                  type="button"
                  className="notif-toast__close"
                  aria-label="Cerrar aviso"
                  onClick={(event) => { event.stopPropagation(); dismissToast(n.id_notificacion); }}
                >
                  ×
                </button>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default NotificationsBell;