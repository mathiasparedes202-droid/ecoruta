import { useEffect, useMemo, useRef, useState } from 'react';
import {
  Search,
  Calendar,
  Eye,
  QrCode,
  AlertCircle,
  CheckCircle2,
  Pencil,
  RotateCcw
} from 'lucide-react';
import { fetchOrders, buildOrderQrPayload, generateOrderQrDataUrl } from '../services/orderService.js';
import { markOrderPaid, updateOrder } from '../services/adminService.js';

function toDateInputValue(value) {
  if (!value || value === '—') return '';
  const text = String(value);
  const isoDate = text.match(/^(\d{4}-\d{2}-\d{2})/);
  if (isoDate) return isoDate[1];
  const localDate = text.match(/^(\d{2})[/-](\d{2})[/-](\d{4})/);
  return localDate ? `${localDate[3]}-${localDate[2]}-${localDate[1]}` : '';
}

export default function MyOrdersPage({ user, onNavigate }) {
  const [myPedidos, setMyPedidos] = useState([]);
  const [loading, setLoading] = useState(true);
  const loadedOnceRef = useRef(false);
  const qrCacheRef = useRef({});

  useEffect(() => {
    const syncOrders = async () => {
      if (!loadedOnceRef.current) setLoading(true);
      try {
        const data = await fetchOrders();
        const all = Array.isArray(data) ? data : data.orders || [];
        const targetCommerce = Number(user?.id_comercio || user?.id_usuario || user?.id || 1);
        const mine = await Promise.all(all
          .filter((pedido) => Number(pedido.id_comercio || 0) === targetCommerce || targetCommerce === 0)
          .map(async (pedido) => {
            const base = {
              id: pedido.id_pedido ?? pedido.id,
              id_comercio: pedido.id_comercio,
              direccionOrigen: pedido.direccion_origen ?? pedido.direccionOrigen ?? '—',
              direccionDestino: pedido.direccion_destino ?? pedido.direccionDestino,
              detallePaquete: pedido.detalle_paquete ?? pedido.detallePaquete ?? '—',
              destinatarioNombre: pedido.destinatario_nombre ?? pedido.destinatarioNombre ?? '',
              destinatarioTelefono: pedido.destinatario_telefono ?? pedido.destinatarioTelefono ?? '',
              pesoKg: pedido.peso_kg ?? pedido.pesoKg ?? null,
              estado: pedido.nombre_estado ?? pedido.estado ?? 'Pendiente',
              id_estado: Number(pedido.id_estado ?? 1),
              fechaSolicitud: pedido.fecha_solicitud ?? pedido.fechaSolicitud ?? '—',
              fechaEntrega: pedido.fecha_entrega ?? pedido.fechaEntrega ?? '—',
              tarifaEcologica: Number(pedido.tarifa_ecologica ?? pedido.tarifaEcologica ?? 0),
              co2Ahorrado: Number(pedido.co2_ahorrado_kg ?? pedido.co2Ahorrado ?? 0),
              distanciaKm: pedido.distancia_km ?? pedido.distanciaKm ?? null,
              observaciones: pedido.observaciones ?? pedido.observaciones ?? null,
              metodoPago: pedido.metodo_pago ?? pedido.metodoPago ?? 'efectivo',
              pagado: Number(pedido.pagado ?? 0) === 1,
              montoEfectivo: pedido.monto_efectivo ?? pedido.montoEfectivo ?? null,
              montoTransferencia: pedido.monto_transferencia ?? pedido.montoTransferencia ?? null,
              vuelto: pedido.vuelto ?? pedido.vuelto ?? null,
              comprobante: pedido.comprobante_transferencia ?? pedido.comprobante ?? null,
              razonSocial: pedido.razon_social ?? pedido.razonSocial ?? '—'
            };
            const qrPayload = buildOrderQrPayload({ id_pedido: base.id, id_comercio: pedido.id_comercio ?? targetCommerce });
            let qrDataUrl = qrCacheRef.current[base.id];
            if (!qrDataUrl) {
              try {
                qrDataUrl = await generateOrderQrDataUrl({ id_pedido: base.id, id_comercio: pedido.id_comercio ?? targetCommerce });
                qrCacheRef.current[base.id] = qrDataUrl;
              } catch {
                qrDataUrl = `data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22><rect width=%22120%22 height=%22120%22 fill=%22%23ffffff%22/><text x=%2260%22 y=%2264%22 text-anchor=%22middle%22 font-size=%2210%22 fill=%22%230B5E47%22>QR no disponible</text></svg>`;
              }
            }
            return { ...base, qrText: qrPayload, qrDataUrl };
          }));
        setMyPedidos(mine);
      } catch {
        setMyPedidos([]);
      } finally {
        setLoading(false);
        loadedOnceRef.current = true;
      }
    };

    syncOrders();
    const interval = setInterval(() => syncOrders(), 1000);
    const onFocus = () => syncOrders();
    window.addEventListener('focus', onFocus);
    document.addEventListener('visibilitychange', onFocus);
    return () => {
      clearInterval(interval);
      window.removeEventListener('focus', onFocus);
      document.removeEventListener('visibilitychange', onFocus);
    };
  }, [user]);

  const [search, setSearch] = useState('');
  const [filterEstado, setFilterEstado] = useState('Todos');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [page, setPage] = useState(1);
  const [selectedOrder, setSelectedOrder] = useState(null);

  const [notice, setNotice] = useState(null);
  const [payDraft, setPayDraft] = useState({ comprobante: '', montoRecibido: '' });
  const [payError, setPayError] = useState('');
  const [editMode, setEditMode] = useState(false);
  const [editDraft, setEditDraft] = useState({ detallePaquete: '', pesoKg: '', destinatarioNombre: '', destinatarioTelefono: '' });
  const [saving, setSaving] = useState(false);

  const PER_PAGE = 5;

  /*
   * =====================================================
   * FILTRADO
   * =====================================================
   */

  const filtered = useMemo(() => {

    let result = myPedidos;

    if (search.trim()) {

      const term = search.trim().toLowerCase();

      result = result.filter((pedido) =>
        String(pedido.id).toLowerCase().includes(term) ||
        String(pedido.direccionDestino || '').toLowerCase().includes(term)
      );
    }

    if (filterEstado && filterEstado !== 'Todos') {
      result = result.filter(
        (pedido) => pedido.estado === filterEstado
      );
    }

    if (dateFrom || dateTo) {
      result = result.filter((pedido) => {
        const orderDate = toDateInputValue(pedido.fechaSolicitud);
        if (!orderDate) return false;
        return (!dateFrom || orderDate >= dateFrom) && (!dateTo || orderDate <= dateTo);
      });
    }

    return result;

  }, [myPedidos, search, filterEstado, dateFrom, dateTo]);

  /*
   * =====================================================
   * PAGINACIÓN
   * =====================================================
   */

  const totalPages = Math.ceil(
    filtered.length / PER_PAGE
  );

  const paged = filtered.slice(
    (page - 1) * PER_PAGE,
    page * PER_PAGE
  );

  /*
   * =====================================================
   * EVENTOS
   * =====================================================
   */

  function handleSearch(value) {
    setSearch(value);
    setPage(1);
  }

  function handleEstado(value) {
    setFilterEstado(value);
    setPage(1);
  }

  function handleDateChange(setter, value) {
    setter(value);
    setPage(1);
  }

  async function handleMarkPaid(pedido) {
    if (!pedido || pedido.pagado || saving) return;
    setPayError('');
    const metodo = pedido.metodoPago || 'efectivo';
    const needsComprobante =
      metodo === 'transferencia' || (metodo === 'mixto' && Number(pedido.montoTransferencia || 0) > 0);
    const needsMontoRecibido = metodo === 'efectivo' || metodo === 'mixto';
    const cashTarget =
      metodo === 'efectivo' ? Number(pedido.tarifaEcologica || 0) : Number(pedido.montoEfectivo || 0);
    try {
      const extra = {};
      if (needsComprobante && String(payDraft.comprobante).trim().length < 5) {
        throw new Error('Anotá el número de comprobante (mínimo 5 caracteres).');
      }
      if (needsMontoRecibido) {
        const recibido = Number(payDraft.montoRecibido);
        if (!(recibido > 0) || recibido < cashTarget) {
          throw new Error(`El efectivo recibido debe ser al menos ₲ ${cashTarget.toLocaleString('es-PY')}.`);
        }
        extra.monto_recibido = recibido;
      }
      if (needsComprobante) extra.comprobante_transferencia = String(payDraft.comprobante).trim();
      setSaving(true);
      await markOrderPaid(pedido.id, true, extra);
      setMyPedidos((list) => list.map((item) => (item.id === pedido.id ? { ...item, pagado: true } : item)));
      setSelectedOrder((order) => (order && order.id === pedido.id ? { ...order, pagado: true } : order));
      setNotice({ type: 'success', text: `¡Pago del pedido #${pedido.id} confirmado! Le llegó el aviso al repartidor.` });
    } catch (error) {
      setPayError(error.message || 'No pudimos registrar el pago. Volvé a intentarlo.');
    } finally {
      setSaving(false);
    }
  }

  function startEdit(order) {
    setEditDraft({
      detallePaquete: order.detallePaquete || '',
      pesoKg: order.pesoKg ?? '',
      destinatarioNombre: order.destinatarioNombre || '',
      destinatarioTelefono: order.destinatarioTelefono || ''
    });
    setEditMode(true);
    setPayError('');
  }

  async function saveRelaunch(order) {
    setPayError('');
    if (!String(editDraft.detallePaquete || '').trim()) {
      setPayError('Describí el paquete para relanzar el pedido.');
      return;
    }
    setSaving(true);
    try {
      await updateOrder(order.id, {
        detalle_paquete: String(editDraft.detallePaquete).trim(),
        ...(editDraft.pesoKg ? { peso_kg: Number(editDraft.pesoKg) } : {}),
        relanzar: true
      });
      setEditMode(false);
      setSelectedOrder(null);
      setNotice({ type: 'success', text: `Pedido #${order.id} relanzado. Los repartidores ya lo ven disponible.` });
    } catch (error) {
      setPayError(error.message || 'No pudimos relanzar el pedido. Volvé a intentarlo.');
    } finally {
      setSaving(false);
    }
  }

  /*
   * =====================================================
   * RENDER
   * =====================================================
   */

  return (
    <div>

      {/* =================================================
          ENCABEZADO
          ================================================= */}

      <div className="panel-title">

        <div>
          <p className="eyebrow">
            Gestión de entregas
          </p>

          <h2>
            Mis Pedidos
          </h2>
        </div>

        <span>
          {filtered.length}{' '}
          {filtered.length === 1
            ? 'pedido'
            : 'pedidos'}
        </span>

      </div>

      {notice && (
        <div className={`mpd-notice ${notice.type === 'error' ? 'mpd-notice--error' : ''}`}>
          {notice.type === 'error' ? <AlertCircle size={17} /> : <CheckCircle2 size={17} />}
          <span>{notice.text}</span>
          <button type="button" className="mpd-notice__close" onClick={() => setNotice(null)} aria-label="Cerrar aviso">x</button>
        </div>
      )}


      {/* =================================================
          FILTROS
          ================================================= */}

      <div
        style={{
          display: 'flex',
          flexWrap: 'wrap',
          gap: '10px',
          marginBottom: '18px'
        }}
      >

        {/* BUSCADOR */}

        <div
          style={{
            position: 'relative',
            flex: '1 1 250px'
          }}
        >

          <Search
            size={16}
            style={{
              position: 'absolute',
              left: '11px',
              top: '50%',
              transform: 'translateY(-50%)',
              color: '#81928b',
              pointerEvents: 'none'
            }}
          />

          <input
            type="text"
            placeholder="Buscar por ID o dirección..."
            value={search}
            onChange={(e) =>
              handleSearch(e.target.value)
            }
            style={{
              background: '#f5f8f4',
              border: '1px solid #d7e4db',
              borderRadius: '9px',
              color: '#173f3b',
              minHeight: '42px',
              outline: 'none',
              padding: '9px 12px 9px 36px',
              width: '100%'
            }}
          />

        </div>


        {/* ESTADO */}

        <select
          value={filterEstado}
          onChange={(e) =>
            handleEstado(e.target.value)
          }
          style={{
            background: '#f5f8f4',
            border: '1px solid #d7e4db',
            borderRadius: '9px',
            color: '#526963',
            minHeight: '42px',
            outline: 'none',
            padding: '9px 12px'
          }}
        >

          <option value="Todos">
            Todos los estados
          </option>

          <option value="Pendiente">
            Pendiente
          </option>

          <option value="Asignado">
            Asignado
          </option>

          <option value="En Camino">
            En Camino
          </option>

          <option value="Entregado">
            Entregado
          </option>

        </select>


        {/* FECHAS */}

        <label className="date-filter">
          <Calendar size={15} aria-hidden="true" />
          <span>Desde</span>
          <input
            type="date"
            value={dateFrom}
            onChange={(e) => handleDateChange(setDateFrom, e.target.value)}
            aria-label="Fecha desde"
          />
        </label>

        <label className="date-filter">
          <span>Hasta</span>
          <input
            type="date"
            value={dateTo}
            min={dateFrom || undefined}
            onChange={(e) => handleDateChange(setDateTo, e.target.value)}
            aria-label="Fecha hasta"
          />
        </label>

      </div>


      {/* =================================================
          TABLA
          ================================================= */}

      <section className="operations-panel">

        <div className="orders-table-wrap">

          <table className="orders-table">

            <thead>

              <tr>

                <th>
                  ID Pedido
                </th>

                <th>
                  Dirección Destino
                </th>

                <th>
                  Estado
                </th>

                <th>
                  F. Solicitud
                </th>

                <th>
                  F. Entrega
                </th>

                <th>
                  Tarifa Eco.
                </th>

                <th>
                  Pago
                </th>

                <th>
                  QR
                </th>
                <th>
                  Ver
                </th>

              </tr>

            </thead>

            <tbody>

              {paged.length === 0 ? (

                <tr>

                  <td
                    colSpan={9}
                    style={{
                      padding: 0
                    }}
                  >

                    <div
                      style={{
                        alignItems: 'center',
                        color: '#81928b',
                        display: 'flex',
                        flexDirection: 'column',
                        padding: '55px 20px',
                        textAlign: 'center'
                      }}
                    >

                      <Search
                        size={26}
                        style={{
                          marginBottom: '10px',
                          opacity: .55
                        }}
                      />

                      <strong
                        style={{
                          color: '#526963',
                          fontFamily: 'Space Grotesk, sans-serif',
                          fontSize: '.9rem'
                        }}
                      >
                        {filterEstado || search
                          ? 'Sin resultados para los filtros seleccionados'
                          : loading
                            ? 'Cargando tus pedidos...'
                            : 'Aún no tienes pedidos registrados'}
                      </strong>

                      <span
                        style={{
                          fontSize: '.76rem',
                          marginTop: '5px'
                        }}
                      >
                        {filterEstado || search
                          ? 'Prueba modificando los filtros o el término de búsqueda.'
                          : 'Cuando registres una solicitud de entrega aparecerá aquí.'}
                      </span>

                    </div>

                  </td>

                </tr>

              ) : (

                paged.map((pedido) => (

                  <tr key={pedido.id}>

                    {/* ID */}

                    <td>

                      <span
                        style={{
                          color: '#28604f',
                          fontFamily: 'Space Grotesk, sans-serif',
                          fontSize: '.75rem',
                          fontWeight: 700
                        }}
                      >
                        {pedido.id}
                      </span>

                    </td>


                    {/* DIRECCIÓN */}

                    <td>

                      <span
                        title={pedido.direccionDestino}
                        style={{
                          display: 'block',
                          fontSize: '.78rem',
                          maxWidth: '220px',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap'
                        }}
                      >
                        {pedido.direccionDestino}
                      </span>

                    </td>


                    {/* ESTADO */}

                    <td>

                      <span className="order-status">
                        {pedido.estado}
                      </span>

                    </td>


                    {/* FECHA SOLICITUD */}

                    <td>

                      <span
                        style={{
                          color: '#708279',
                          fontSize: '.75rem',
                          whiteSpace: 'nowrap'
                        }}
                      >
                        {pedido.fechaSolicitud}
                      </span>

                    </td>


                    {/* FECHA ENTREGA */}

                    <td>

                      <span
                        style={{
                          color: '#708279',
                          fontSize: '.75rem',
                          whiteSpace: 'nowrap'
                        }}
                      >
                        {pedido.fechaEntrega ?? '—'}
                      </span>

                    </td>


                    {/* TARIFA */}

                    <td>

                      <span
                        style={{
                          color: '#173f3b',
                          fontFamily: 'Space Grotesk, sans-serif',
                          fontSize: '.78rem',
                          fontWeight: 700,
                          whiteSpace: 'nowrap'
                        }}
                      >
                        $
                        {Number(
                          pedido.tarifaEcologica
                        ).toFixed(2)}
                      </span>

                    </td>


                    {/* PAGO */}

                    <td>

                      <span
                        className={`order-status ${pedido.pagado ? 'order-status--paid' : 'order-status--unpaid'}`}
                        title={
                          pedido.metodoPago === 'mixto'
                            ? (pedido.pagado
                              ? `Cobrado: Efectivo ₲ ${Number(pedido.montoEfectivo || 0).toLocaleString('es-PY')} + Transf. ₲ ${Number(pedido.montoTransferencia || 0).toLocaleString('es-PY')}${Number(pedido.vuelto) > 0 ? ` · Vuelto ₲ ${Number(pedido.vuelto).toLocaleString('es-PY')}` : ''}`
                              : 'Mixto pendiente: falta cobrar la parte en efectivo al entregar')
                            : (pedido.metodoPago === 'transferencia'
                              ? (pedido.pagado ? `Transferencia confirmada${pedido.comprobante ? ` · Comp.: ${pedido.comprobante}` : ''}` : 'Transferencia pendiente de confirmar')
                              : (pedido.pagado ? 'Efectivo cobrado' : 'Se cobra en efectivo al entregar'))}
                      >
                        {pedido.metodoPago === 'mixto'
                          ? (pedido.pagado ? 'Mixto · Cobrado' : 'Mixto · Pte.')
                          : (pedido.metodoPago === 'transferencia'
                            ? (pedido.pagado ? 'Transferencia · Pagado' : 'Transferencia · Pte.')
                            : (pedido.pagado ? 'Efectivo · Cobrado' : 'Efectivo al entregar'))}
                      </span>

                    </td>


                    {/* QR */}

                    <td>

                      <button
                        type="button"
                        title="Ver código QR del pedido"
                        aria-label={`Ver código QR del pedido ${pedido.id}`}
                        onClick={() => setSelectedOrder(pedido)}
                        style={{
                          alignItems: 'center',
                          background: '#eaf6f1',
                          border: 0,
                          borderRadius: '8px',
                          color: '#173f3b',
                          cursor: 'pointer',
                          display: 'inline-flex',
                          height: '34px',
                          justifyContent: 'center',
                          width: '34px'
                        }}
                      >
                        <QrCode size={15} />
                      </button>

                    </td>

                    {/* VER */}

                    <td>

                      <button
                        type="button"
                        title="Ver detalles"
                        aria-label={`Ver detalles del pedido ${pedido.id}`}
                        onClick={() => setSelectedOrder(pedido)}
                        style={{
                          alignItems: 'center',
                          background: 'transparent',
                          border: 0,
                          borderRadius: '7px',
                          color: '#708279',
                          cursor: 'pointer',
                          display: 'flex',
                          height: '34px',
                          justifyContent: 'center',
                          width: '34px'
                        }}
                      >

                        <Eye size={15} />

                      </button>

                    </td>

                  </tr>

                ))

              )}

            </tbody>

          </table>

        </div>


        {/* =================================================
            PAGINACIÓN
            ================================================= */}

        {totalPages > 0 && (

          <div
            style={{
              alignItems: 'center',
              borderTop: '1px solid #dce7df',
              display: 'flex',
              justifyContent: 'space-between',
              padding: '13px 16px'
            }}
          >

            <span
              style={{
                color: '#81928b',
                fontSize: '.73rem'
              }}
            >
              Página {page} de {totalPages}
            </span>


            <div
              style={{
                display: 'flex',
                gap: '4px'
              }}
            >

              <button
                type="button"
                disabled={page === 1}
                onClick={() =>
                  setPage((current) => current - 1)
                }
              >
                Anterior
              </button>


              {Array.from(
                { length: totalPages },
                (_, index) => index + 1
              ).map((number) => (

                <button
                  key={number}
                  type="button"
                  className={
                    page === number
                      ? 'dashboard-nav__active'
                      : ''
                  }
                  onClick={() =>
                    setPage(number)
                  }
                >
                  {number}
                </button>

              ))}


              <button
                type="button"
                disabled={page === totalPages}
                onClick={() =>
                  setPage((current) => current + 1)
                }
              >
                Siguiente
              </button>

            </div>

          </div>

        )}

      </section>

      {selectedOrder && (
        <div
          className="auth-backdrop"
          role="presentation"
          onMouseDown={(event) => event.target === event.currentTarget && setSelectedOrder(null)}
        >
          <div className="account-modal" style={{ width: 'min(620px, 100%)' }}>
            <button
              className="auth-close"
              type="button"
              onClick={() => setSelectedOrder(null)}
              aria-label="Cerrar detalles"
            >x</button>

            <div className="account-panel" style={{ width: '100%' }}>
              <p className="eyebrow">Detalle de pedido</p>
              <h2>Pedido #{selectedOrder.id}</h2>

              <div className="account-intro" style={{ marginBottom: '18px' }}>
                <span className="order-status">{selectedOrder.estado}</span>
                {' '}· {selectedOrder.razonSocial}
              </div>

              <div
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  gap: '10px',
                  padding: '18px',
                  background: '#f2f7f2',
                  border: '1px solid #dce9df',
                  borderRadius: '12px',
                  marginBottom: '18px'
                }}
              >
                {selectedOrder.qrDataUrl ? (
                  <img
                    src={selectedOrder.qrDataUrl}
                    alt={`Código QR del pedido ${selectedOrder.id}`}
                    style={{ width: '260px', height: '260px', borderRadius: '8px', background: '#fff', padding: '8px' }}
                  />
                ) : (
                  <p className="status">Generando QR...</p>
                )}
                <p style={{ margin: 0, color: '#526963', fontSize: '.82rem', fontWeight: 700 }}>
                  Pedido #{selectedOrder.id} · Escanéalo con la app del repartidor
                </p>
                <button
                  className="new-order-calculate"
                  type="button"
                  onClick={() => {
                    if (!selectedOrder.qrDataUrl) return;
                    const link = document.createElement('a');
                    link.href = selectedOrder.qrDataUrl;
                    link.download = `EcoRuta-QR-${selectedOrder.id}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                  }}
                >
                  Descargar QR
                </button>
              </div>

              <div className="orders-table-wrap">
                <table className="orders-table">
                  <tbody>
                    <tr>
                      <th style={{ width: '190px', textAlign: 'left' }}>Origen</th>
                      <td>{selectedOrder.direccionOrigen}</td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Destino</th>
                      <td>{selectedOrder.direccionDestino}</td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Paquete</th>
                      <td>{selectedOrder.detallePaquete}</td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Peso</th>
                      <td>{selectedOrder.pesoKg ? `${Number(selectedOrder.pesoKg)} kg` : '—'}</td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Distancia</th>
                      <td>{selectedOrder.distanciaKm != null ? `${Number(selectedOrder.distanciaKm)} km` : '—'}</td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Tarifa ecológica</th>
                      <td>${Number(selectedOrder.tarifaEcologica || 0).toFixed(2)}</td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Método de pago</th>
                      <td>
                        {selectedOrder.metodoPago === 'mixto'
                          ? `Mixto: Efectivo ₲ ${Number(selectedOrder.montoEfectivo || 0).toLocaleString('es-PY')} + Transferencia ₲ ${Number(selectedOrder.montoTransferencia || 0).toLocaleString('es-PY')}`
                          : (selectedOrder.metodoPago === 'transferencia' ? 'Transferencia' : 'Efectivo')}
                      </td>
                    </tr>
                    {Number(selectedOrder.vuelto) > 0 && (
                      <tr>
                        <th style={{ textAlign: 'left' }}>Vuelto entregado</th>
                        <td>₲ {Number(selectedOrder.vuelto).toLocaleString('es-PY')}</td>
                      </tr>
                    )}
                    {selectedOrder.comprobante && (
                      <tr>
                        <th style={{ textAlign: 'left' }}>N° comprobante</th>
                        <td>{selectedOrder.comprobante}</td>
                      </tr>
                    )}
                    <tr>
                      <th style={{ textAlign: 'left' }}>Estado del pago</th>
                      <td>
                        <span className={`order-status ${selectedOrder.pagado ? 'order-status--paid' : 'order-status--unpaid'}`}>
                          {selectedOrder.pagado ? 'Pagado' : 'Pendiente'}
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>CO₂ ahorrado</th>
                      <td>{Number(selectedOrder.co2Ahorrado || 0).toFixed(3)} kg</td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Fecha solicitud</th>
                      <td>{selectedOrder.fechaSolicitud}</td>
                    </tr>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Fecha entrega</th>
                      <td>{selectedOrder.fechaEntrega ?? '—'}</td>
                    </tr>
                    {selectedOrder.observaciones && (
                      <tr>
                        <th style={{ textAlign: 'left' }}>Observaciones</th>
                        <td>{selectedOrder.observaciones}</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '18px' }}>
                {!selectedOrder.pagado && Number(selectedOrder.id_estado ?? 1) !== 5 && (
                  <div className="mpd-pay" style={{ flexDirection: 'column', marginRight: 'auto' }}>
                    {(() => {
                      const metodo = selectedOrder.metodoPago || 'efectivo';
                      const needsComprobante = metodo === 'transferencia' || (metodo === 'mixto' && Number(selectedOrder.montoTransferencia || 0) > 0);
                      const needsMontoRecibido = metodo === 'efectivo' || metodo === 'mixto';
                      const cashTarget = metodo === 'efectivo' ? Number(selectedOrder.tarifaEcologica || 0) : Number(selectedOrder.montoEfectivo || 0);
                      const recibido = Number(payDraft.montoRecibido);
                      const vuelto = recibido > 0 && needsMontoRecibido ? Math.max(0, recibido - cashTarget) : 0;
                      return (
                        <>
                          {needsComprobante && (
                            <label style={{ display: 'grid', gap: '5px', fontSize: '.76rem', fontWeight: 700, color: '#64716b' }}>
                              Número de comprobante de la transferencia
                              <input
                                value={payDraft.comprobante}
                                onChange={(e) => { setPayDraft((d) => ({ ...d, comprobante: e.target.value })); if (payError) setPayError(''); }}
                                placeholder="Ej: TRX-20260905-1234"
                                style={{ background: '#f5f8f4', border: '1px solid #d7e4db', borderRadius: '8px', color: '#173f3b', font: 'inherit', padding: '9px 11px' }}
                              />
                            </label>
                          )}
                          {needsMontoRecibido && (
                            <label style={{ display: 'grid', gap: '5px', fontSize: '.76rem', fontWeight: 700, color: '#64716b' }}>
                              Efectivo recibido al entregar
                              <input
                                type="number"
                                min="0"
                                step="500"
                                value={payDraft.montoRecibido}
                                onChange={(e) => { setPayDraft((d) => ({ ...d, montoRecibido: e.target.value })); if (payError) setPayError(''); }}
                                placeholder={`₲ mínimo ${cashTarget.toLocaleString('es-PY')}`}
                                style={{ background: '#f5f8f4', border: '1px solid #d7e4db', borderRadius: '8px', color: '#173f3b', font: 'inherit', padding: '9px 11px' }}
                              />
                            </label>
                          )}
                          {vuelto > 0 && (
                            <small style={{ color: '#28604f', fontWeight: 700 }}>
                              Vuelto a entregar al cliente: ₲ {vuelto.toLocaleString('es-PY')}
                            </small>
                          )}
                        </>
                      );
                    })()}
                    {payError && (
                      <small className="mpd-pay__error">
                        <AlertCircle size={14} /> {payError}
                      </small>
                    )}
                  </div>
                )}
                {!selectedOrder.pagado && Number(selectedOrder.id_estado ?? 1) !== 5 && (
                  <button
                    className="primary-action"
                    type="button"
                    disabled={saving}
                    onClick={() => handleMarkPaid(selectedOrder)}
                  >
                    {saving ? 'Confirmando…' : (selectedOrder.metodoPago === 'transferencia'
                      ? 'Confirmar transferencia recibida'
                      : 'Marcar pago recibido')}
                  </button>
                )}
                <button
                  className="primary-action"
                  type="button"
                  onClick={() => {
                    const payload = buildOrderQrPayload(selectedOrder);
                    navigator.clipboard?.writeText(payload);
                    setNotice({ type: 'success', text: `QR del pedido ${selectedOrder.id} copiado al portapapeles.` });
                  }}
                >
                  Copiar QR
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedOrder(null)}
                  className="new-order-cancel"
                >
                  Cerrar
                </button>
              </div>

              {Number(selectedOrder.id_estado ?? 1) === 5 && !editMode && (
                <div className="mpd-relaunch">
                  <p>
                    <RotateCcw size={15} /> Este pedido está <strong>cancelado</strong>. Podés editarlo y volver a lanzarlo para los repartidores.
                  </p>
                  <button className="primary-action" type="button" onClick={() => startEdit(selectedOrder)}>
                    <Pencil size={15} /> Editar y relanzar
                  </button>
                </div>
              )}

              {Number(selectedOrder.id_estado ?? 1) === 5 && editMode && (
                <div className="mpd-relaunch mpd-relaunch--form">
                  <p><strong>Relanzando el pedido #{selectedOrder.id}</strong></p>
                  <label style={{ display: 'grid', gap: '5px', fontSize: '.76rem', fontWeight: 700, color: '#64716b' }}>
                    Descripción del paquete
                    <input
                      value={editDraft.detallePaquete}
                      onChange={(e) => setEditDraft((d) => ({ ...d, detallePaquete: e.target.value }))}
                      style={{ background: '#f5f8f4', border: '1px solid #d7e4db', borderRadius: '8px', color: '#173f3b', font: 'inherit', padding: '9px 11px' }}
                    />
                  </label>
                  <label style={{ display: 'grid', gap: '5px', fontSize: '.76rem', fontWeight: 700, color: '#64716b' }}>
                    Peso (kg)
                    <input
                      type="number"
                      min="0.1"
                      max="50"
                      step="0.1"
                      value={editDraft.pesoKg}
                      onChange={(e) => setEditDraft((d) => ({ ...d, pesoKg: e.target.value }))}
                      placeholder="Ej: 2.5"
                      style={{ background: '#f5f8f4', border: '1px solid #d7e4db', borderRadius: '8px', color: '#173f3b', font: 'inherit', padding: '9px 11px' }}
                    />
                  </label>
                  <div style={{ display: 'flex', gap: '10px', justifyContent: 'flex-end' }}>
                    <button
                      type="button"
                      className="new-order-cancel"
                      onClick={() => setEditMode(false)}
                      disabled={saving}
                    >
                      Cancelar
                    </button>
                    <button
                      className="primary-action"
                      type="button"
                      disabled={saving}
                      onClick={() => saveRelaunch(selectedOrder)}
                    >
                      {saving ? 'Relanzando…' : 'Guardar y relanzar'}
                    </button>
                  </div>
                  {payError && (
                    <small className="mpd-pay__error">
                      <AlertCircle size={14} /> {payError}
                    </small>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

    </div>
  );
}

