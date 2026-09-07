import { useEffect, useState } from 'react';
import {
  AlertCircle,
  Bike,
  Car,
  CheckCircle2,
  ClipboardList,
  MapPin,
  MoveRight,
  RefreshCw,
  UserCheck,
} from 'lucide-react';
import { fetchOrders } from '../services/dashboardService.js';
import { assignOrder, fetchRepartidores, markOrderPaid } from '../services/adminService.js';
import { formatGs } from '../lib/format.js';

const estadoLabel = {
  1: 'Pendiente',
  2: 'Asignado',
  3: 'En camino',
  4: 'Entregado',
  5: 'Cancelado',
};

const estadoTone = {
  1: 'estado--pendiente',
  2: 'estado--asignado',
  3: 'estado--camino',
  4: 'estado--entregado',
  5: 'estado--cancelado',
};

export default function AdminOrders({ user }) {
  const [orders, setOrders] = useState([]);
  const [repartidores, setRepartidores] = useState([]);
  const [loading, setLoading] = useState(true);
  const [savingId, setSavingId] = useState(null);
  const [error, setError] = useState('');
  const [selection, setSelection] = useState({});
  const [message, setMessage] = useState('');
  const [payTarget, setPayTarget] = useState(null);
  const [payDraft, setPayDraft] = useState({ comprobante: '', montoRecibido: '' });
  const [payError, setPayError] = useState('');

  async function load() {
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const [data, rep] = await Promise.all([fetchOrders(), fetchRepartidores()]);
      const list = Array.isArray(data) ? data : data.orders || [];
      const reps = Array.isArray(rep) ? rep : rep.repartidores || [];
      setOrders(list);
      setRepartidores(reps);
      // Preseleccionar el repartidor que ya tiene cada pedido
      const next = {};
      list.forEach((o) => {
        if (o.id_repartidor) next[o.id_pedido] = Number(o.id_repartidor);
      });
      setSelection(next);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No pudimos cargar los pedidos. Verificá la conexión.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  const active = orders.filter((o) => Number(o.id_estado) !== 4 && Number(o.id_estado) !== 5);
  const done = orders.filter((o) => {
    const estado = Number(o.id_estado);
    return estado === 4 || estado === 5;
  });

  function paymentRequirements(order) {
    const metodo = order.metodo_pago || 'efectivo';
    return {
      metodo,
      needsComprobante: metodo === 'transferencia' || (metodo === 'mixto' && Number(order.monto_transferencia || 0) > 0),
      needsMontoRecibido: metodo === 'efectivo' || metodo === 'mixto',
      cashTarget: metodo === 'efectivo' ? Number(order.tarifa_ecologica || 0) : Number(order.monto_efectivo || 0),
    };
  }

  function openPay(order) {
    setPayTarget(order);
    setPayDraft({ comprobante: '', montoRecibido: '' });
    setPayError('');
  }

  async function confirmPay(order) {
    const req = paymentRequirements(order);
    setSavingId(`pago-${order.id_pedido}`);
    setPayError('');
    try {
      const extra = {};
      if (req.needsComprobante && String(payDraft.comprobante).trim().length < 5) {
        throw new Error('Anotá el número de comprobante (mínimo 5 caracteres).');
      }
      if (req.needsMontoRecibido) {
        const recibido = Number(payDraft.montoRecibido);
        if (!(recibido > 0) || recibido < req.cashTarget) {
          throw new Error(`El efectivo recibido debe ser al menos ₲ ${formatGs(req.cashTarget)}.`);
        }
        extra.monto_recibido = recibido;
      }
      if (req.needsComprobante) extra.comprobante_transferencia = String(payDraft.comprobante).trim();
      await markOrderPaid(order.id_pedido, true, extra);
      setMessage(`¡Pago del pedido #${order.id_pedido} confirmado! El repartidor ya sabe que está al día.`);
      setPayTarget(null);
      await load();
    } catch (err) {
      setPayError(err instanceof Error ? err.message : 'No pudimos registrar el pago.');
    } finally {
      setSavingId(null);
    }
  }

  function methodLabel(order) {
    const metodo = order.metodo_pago || 'efectivo';
    if (metodo === 'efectivo') return 'Efectivo';
    if (metodo === 'transferencia') return 'Transf.';
    return 'Mixto';
  }

  function paymentTitle(order) {
    const partes = [];
    partes.push(order.metodo_pago === 'mixto'
      ? `Mixto · Eff ₲ ${formatGs(order.monto_efectivo)} + Transf. ₲ ${formatGs(order.monto_transferencia)}`
      : methodLabel(order));
    if (Number(order.vuelto) > 0) partes.push(`Vuelto ₲ ${formatGs(order.vuelto)}`);
    if (order.comprobante_transferencia) partes.push(`Comp.: ${order.comprobante_transferencia}`);
    return partes.join(' · ');
  }

  async function handleAssign(order) {
    const repartidorId = selection[order.id_pedido];
    if (!repartidorId) {
      setMessage('Elegí un repartidor antes de asignar el pedido.');
      return;
    }
    setSavingId(order.id_pedido);
    setError('');
    setMessage('');
    try {
      await assignOrder(order.id_pedido, {
        id_repartidor: Number(repartidorId),
        id_usuario_cambio: Number(user?.id_usuario || user?.id || 0),
      });
      setMessage(`Pedido #${order.id_pedido} asignado. El repartidor ya recibió el aviso.`);
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No pudimos asignar el pedido.');
    } finally {
      setSavingId(null);
    }
  }

  if (loading) {
    return (
      <section className="operations-panel">
        <p className="eyebrow">ASIGNACIÓN DE PEDIDOS</p>
        <h2>Cargando pedidos y repartidores…</h2>
      </section>
    );
  }

  return (
    <section className="operations-panel admin-assign">
      <div className="panel-title">
        <div>
          <p className="eyebrow">ASIGNACIÓN DE PEDIDOS</p>
          <h2>Asignar pedidos a repartidores</h2>
          <p>Seleccioná un repartidor para cada entrega pendiente y organizá el recorrido del turno.</p>
        </div>
        <button className="admin-assign__refresh" type="button" onClick={load}>
          <RefreshCw size={15} /> Actualizar
        </button>
      </div>

      {error && (
        <div className="admin-overview__error">
          <AlertCircle size={18} />
          <span>{error}</span>
        </div>
      )}
      {message && (
        <div className="admin-assign__success">
          <CheckCircle2 size={18} />
          <span>{message}</span>
        </div>
      )}

      <div className="admin-assign__layout">
        {/* Repartidores */}
        <aside className="admin-assign__repartidores">
          <h3>Repartidores</h3>
          <div className="repartidor-list">
            {repartidores.length === 0 && (
              <p className="admin-assign__empty">
                <UserCheck size={16} /> Sin repartidores registrados.
              </p>
            )}
            {repartidores.map((r) => (
              <div className={`repartidor-card ${r.disponible ? '' : 'repartidor-card--pausado'}`} key={r.id_repartidor}>
                <span className="repartidor-card__vehiculo">
                  {r.tipo_vehiculo === 'Vehículo Eléctrico' ? <Car size={17} /> : <Bike size={17} />}
                </span>
                <div>
                  <strong>{r.nombre_completo}</strong>
                  <small>{r.tipo_vehiculo}{r.matricula ? ` · ${r.matricula}` : ''}</small>
                  <em className={r.disponible ? 'repartidor-card__disponible' : 'repartidor-card__pausado-tag'}>
                    {r.disponible ? 'Disponible' : 'En pausa'}
                  </em>
                </div>
                <span className="repartidor-card__stats">
                  <b>{r.entregas_en_curso}</b> en curso
                  <b>{r.entregas_hoy}</b> hoy
                </span>
              </div>
            ))}
          </div>
        </aside>

        {/* Pedidos por asignar */}
        <div className="admin-assign__pedidos">
          <h3>
            <ClipboardList size={16} /> Entregas por organizar <span>({active.length})</span>
          </h3>

          {active.length === 0 && !loading && (
            <div className="admin-assign__empty">
              <CheckCircle2 size={20} />
              <p>No hay pedidos pendientes por asignar. Todos los turnos están al día.</p>
            </div>
          )}

          <div className="assign-orders-list">
            {active.map((order) => (
              <div className="assign-order" key={order.id_pedido}>
                <div className="assign-order__head">
                  <strong>Pedido #{order.id_pedido}</strong>
                  <span className={`estado ${estadoTone[order.id_estado] || 'estado--pendiente'}`}>
                    {estadoLabel[order.id_estado] || 'Pendiente'}
                  </span>
                </div>
                <p className="assign-order__detail">{order.detalle_paquete || 'Paquete EcoRuta'}</p>
                <p className="assign-order__store">{order.razon_social || 'Comercio EcoRuta'}</p>
                <div className="assign-order__addresses">
                  <span>
                    <MapPin size={14} /> {order.direccion_origen}
                  </span>
                  <span className="assign-order__arrow"><MoveRight size={14} /></span>
                  <span>
                    <MapPin size={14} /> {order.direccion_destino}
                  </span>
                </div>
                <div className="assign-order__meta">
                  <span>{Number(order.distancia_km || 0).toFixed(1)} km</span>
                  <span>{Number(order.peso_kg || 0)} kg</span>
                  <span>{formatGs(order.tarifa_ecologica)} ₲</span>
                  <span>{Number(order.co2_ahorrado_kg || 0).toFixed(2)} kg CO₂</span>
                  <span
                    className={Number(order.pagado) === 1 ? 'pago-chip pago-chip--pagado' : 'pago-chip pago-chip--pendiente'}
                    title={paymentTitle(order)}
                  >
                    {Number(order.pagado) === 1
                      ? `${methodLabel(order)} · Pagado`
                      : `${methodLabel(order)} · Pte.`}
                  </span>
                </div>
                <div className="assign-order__actions">
                  {Number(order.pagado) !== 1 && Number(order.id_estado) !== 5 && (
                    <button
                      type="button"
                      className="assign-order__pay"
                      disabled={savingId === `pago-${order.id_pedido}`}
                      onClick={() => openPay(order)}
                    >
                      {savingId === `pago-${order.id_pedido}` ? 'Registrando…' : 'Marcar pagado'}
                    </button>
                  )}
                  <select
                    aria-label={`Repartidor para el pedido ${order.id_pedido}`}
                    value={selection[order.id_pedido] || ''}
                    onChange={(e) =>
                      setSelection((prev) => ({ ...prev, [order.id_pedido]: e.target.value }))
                    }
                  >
                    <option value="">— Elegir repartidor —</option>
                    {repartidores.map((r) => (
                      <option key={r.id_repartidor} value={r.id_repartidor}>
                        {r.nombre_completo} ({r.tipo_vehiculo})
                      </option>
                    ))}
                  </select>
                  <button
                    type="button"
                    className="assign-order__btn"
                    disabled={savingId === order.id_pedido || !selection[order.id_pedido]}
                    onClick={() => handleAssign(order)}
                  >
                    {savingId === order.id_pedido ? 'Asignando…' : order.id_repartidor ? 'Reasignar' : 'Asignar'}
                  </button>
                </div>
              </div>
            ))}
          </div>

          {done.length > 0 && (
            <details className="admin-assign__done">
              <summary>Ver entregas realizadas y canceladas ({done.length})</summary>
              <div className="assign-orders-list assign-orders-list--done">
                {done.map((order) => (
                  <div className="assign-order" key={order.id_pedido}>
                    <div className="assign-order__head">
                      <strong>Pedido #{order.id_pedido}</strong>
                      <span className={`estado ${estadoTone[order.id_estado] || 'estado--entregado'}`}>
                        {estadoLabel[order.id_estado] || 'Entregado'}
                      </span>
                    </div>
                    <p className="assign-order__detail">{order.detalle_paquete || 'Paquete EcoRuta'}</p>
                    <p className="assign-order__store">{order.razon_social || 'Comercio EcoRuta'}</p>
                  </div>
                ))}
              </div>
            </details>
          )}
        </div>
      </div>

      {payTarget && (() => {
        const req = paymentRequirements(payTarget);
        const recibido = Number(payDraft.montoRecibido);
        const vuelto = recibido > 0 && req.needsMontoRecibido ? Math.max(0, recibido - req.cashTarget) : 0;
        return (
          <div className="auth-backdrop" onClick={() => setPayTarget(null)}>
            <div className="account-modal account-modal--pay" onClick={(e) => e.stopPropagation()}>
              <h3>Confirmar pago · Pedido #{payTarget.id_pedido}</h3>
              <p className="account-modal__sub">Tarifa: ₲ {formatGs(payTarget.tarifa_ecologica)}
                {payTarget.metodo_pago === 'mixto' && (
                  <>
                    {' '}· Efectivo ₲ {formatGs(payTarget.monto_efectivo)} · Transferencia ₲ {formatGs(payTarget.monto_transferencia)}
                  </>
                )}
              </p>

              {req.needsComprobante && (
                <label className="account-modal__field">
                  <span>Número de comprobante de la transferencia</span>
                  <input
                    value={payDraft.comprobante}
                    onChange={(e) => setPayDraft((p) => ({ ...p, comprobante: e.target.value }))}
                    placeholder="Ej: TRX-20260905-1234"
                    autoFocus
                  />
                </label>
              )}

              {req.needsMontoRecibido && (
                <label className="account-modal__field">
                  <span>Efectivo recibido ({payTarget.metodo_pago === 'mixto' ? 'parte en efectivo' : 'total'})</span>
                  <input
                    type="number"
                    min="0"
                    step="500"
                    value={payDraft.montoRecibido}
                    onChange={(e) => setPayDraft((p) => ({ ...p, montoRecibido: e.target.value }))}
                    placeholder="₲ recibido al entregar"
                  />
                </label>
              )}

              {vuelto > 0 && (
                <p className="account-modal__sub account-modal__sub--vuelto">Vuelto a entregar al cliente: ₲ {formatGs(vuelto)}</p>
              )}

              {payError && (
                <div className="account-modal__error">
                  <AlertCircle size={16} /> {payError}
                </div>
              )}

              <div className="account-modal__actions">
                <button type="button" className="account-modal__ghost" onClick={() => setPayTarget(null)}>Cancelar</button>
                <button
                  type="button"
                  className="account-modal__primary"
                  disabled={savingId === `pago-${payTarget.id_pedido}`}
                  onClick={() => confirmPay(payTarget)}
                >
                  {savingId === `pago-${payTarget.id_pedido}` ? 'Registrando…' : 'Confirmar pago'}
                </button>
              </div>
            </div>
          </div>
        );
      })()}
    </section>
  );
}