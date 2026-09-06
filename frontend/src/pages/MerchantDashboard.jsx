import { useEffect, useRef, useState } from 'react';
import {
  Search,
  Calendar,
  Eye,
  QrCode,
  ArrowRight
} from 'lucide-react';
import { fetchOrders, buildOrderQrPayload, generateOrderQrDataUrl } from '../services/orderService.js';

function normalizeOrder(raw) {
  return {
    id: raw.id_pedido ?? raw.id,
    id_comercio: raw.id_comercio,
    direccionDestino: raw.direccion_destino ?? raw.direccionDestino,
    estado: raw.nombre_estado ?? raw.estado ?? 'Pendiente',
    tarifaEcologica: Number(raw.tarifa_ecologica ?? raw.tarifaEcologica ?? 0),
    co2Ahorrado: Number(raw.co2_ahorrado_kg ?? raw.co2Ahorrado ?? 0),
  };
}

export default function MerchantDashboard({
  user,
  onNavigate
}) {
  const [myPedidos, setMyPedidos] = useState([]);
  const [loading, setLoading] = useState(true);
  const loadedOnceRef = useRef(false);
  const qrCacheRef = useRef({});
  const [selectedOrder, setSelectedOrder] = useState(null);

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
              pesoKg: pedido.peso_kg ?? pedido.pesoKg ?? null,
              estado: pedido.nombre_estado ?? pedido.estado ?? 'Pendiente',
              fechaSolicitud: pedido.fecha_solicitud ?? pedido.fechaSolicitud ?? '—',
              fechaEntrega: pedido.fecha_entrega ?? pedido.fechaEntrega ?? '—',
              tarifaEcologica: Number(pedido.tarifa_ecologica ?? pedido.tarifaEcologica ?? 0),
              co2Ahorrado: Number(pedido.co2_ahorrado_kg ?? pedido.co2Ahorrado ?? 0),
              distanciaKm: pedido.distancia_km ?? pedido.distanciaKm ?? null,
              observaciones: pedido.observaciones ?? pedido.observaciones ?? null,
              razonSocial: pedido.razon_social ?? pedido.razonSocial ?? '—',
              cliente: pedido.cliente_nombre ?? pedido.destinatario_nombre ?? ''
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

  const stats = [
    {
      label: 'Total Pedidos',
      value: String(myPedidos.length),
      sub: 'Histórico'
    },
    {
      label: 'Pendientes',
      value: String(
        myPedidos.filter(
          (p) => p.estado === 'Pendiente'
        ).length
      ),
      sub: 'Esperando asignación'
    },
    {
      label: 'En Camino',
      value: String(
        myPedidos.filter(
          (p) => p.estado === 'En Camino'
        ).length
      ),
      sub: 'En tránsito'
    },
    {
      label: 'Entregados',
      value: String(
        myPedidos.filter(
          (p) => p.estado === 'Entregado'
        ).length
      ),
      sub: 'Completados'
    },
    {
      label: 'Total Invertido',
      value: `GS: ${myPedidos
        .reduce(
          (s, p) => s + Number(p.tarifaEcologica || 0),
          0
        )
        .toFixed(0)}`,
      sub: 'En entregas eco.'
    },
    {
      label: 'CO₂ Ahorrado',
      value: `${myPedidos
        .reduce(
          (s, p) => s + Number(p.co2Ahorrado || 0),
          0
        )
        .toFixed(2)} kg`,
      sub: 'Tu huella verde'
    }
  ];

  return (
    <div className="merchant-dashboard">

      <div className="panel-title">

        <div>
          <p className="merchant-kicker">Resumen ejecutivo <span className="merchant-kicker__dot" aria-hidden="true"></span></p>
          <h2>
            {user.nombre_completo}
          </h2>

          <p>
            Portal del comercio · Panel principal
          </p>
        </div>

        <button
          onClick={() => onNavigate('new-order')}
          className="primary-action"
        >
          Nueva Solicitud de Entrega
        </button>

      </div>


      {/* ESTADÍSTICAS */}

      <div className="dashboard-stats">

        {stats.map((stat) => (
          <div key={stat.label}>

            <strong>
              {stat.value}
            </strong>

            <span>
              {stat.label}
            </span>

            <small>
              {stat.sub}
            </small>

          </div>
        ))}

      </div>


      {/* PEDIDOS RECIENTES */}

      <div className="operations-panel">

        <div className="panel-title">

          <div>
            <h2>
              Ultimos Pedidos
            </h2>
          </div>

          <button
            type="button"
            onClick={() =>
              onNavigate('my-orders')
            }
          >
            Ver todos
          </button>

        </div>


        {myPedidos.length === 0 ? (

          <p className="status">
            {loading ? 'Cargando pedidos...' : 'Aun no tienes pedidos registrados.'}
          </p>

        ) : (
          <section className="operations-panel"> 

          <div className="orders-table-wrap">

            <table className="orders-table">

              <thead>
                <tr>
                  <th>
                    ID
                  </th>
                  <th>
                    Destino
                  </th>
                  <th>
                    Estado
                  </th>
                  <th>
                    Tarifa Eco.
                  </th>
                  <th>
                    Qr
                  </th>
                </tr>
              </thead>

              <tbody>

                {myPedidos.slice(0, 5).map((pedido) => (
                  
                  <tr key={pedido.id}>
                    
                    <td>
                      {pedido.id}
                    </td>

                    <td>
                      <div className="merchant-order-dest">
                        {pedido.direccionDestino}
                        {pedido.cliente && (
                          <small>Para: {pedido.cliente}</small>
                        )}
                      </div>
                    </td>

                    <td>
                      <span className="order-status">
                        {pedido.estado}
                      </span>
                    </td>

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
                      
                  </tr>

                ))}

              </tbody>

            </table>

          </div>
          </section>  
        )}

      </div>


      {selectedOrder && (
        <div
          className="auth-backdrop"
          role="presentation"
          onMouseDown={(event) => event.target === event.currentTarget && setSelectedOrder(null)}
        >
          <div className="account-modal" style={{ width: 'min(460px, 100%)' }}>
            <button
              className="auth-close"
              type="button"
              onClick={() => setSelectedOrder(null)}
              aria-label="Cerrar código QR"
            >
              x
            </button>
            <div className="account-panel" style={{ textAlign: 'center', width: '100%' }}>
              <p className="eyebrow">Código QR del pedido</p>
              <h2>Pedido #{selectedOrder.id}</h2>
              {selectedOrder.qrDataUrl ? (
                <img
                  src={selectedOrder.qrDataUrl}
                  alt={`Código QR del pedido ${selectedOrder.id}`}
                  style={{ background: '#fff', border: '1px solid #dce7df', borderRadius: '12px', display: 'block', margin: '20px auto', padding: '10px', width: '260px' }}
                />
              ) : (
                <p className="status">Generando QR...</p>
              )}
              <p className="account-intro">Escanea este código con la aplicación del repartidor.</p>
              {selectedOrder.qrDataUrl && (
                <a
                  className="primary-action"
                  href={selectedOrder.qrDataUrl}
                  download={`EcoRuta-QR-${selectedOrder.id}.png`}
                >
                  Descargar QR
                </a>
              )}
            </div>
          </div>
        </div>
      )}


      {/* ACCIONES RÁPIDAS */}

      <div className="module-grid">

        <article className="module-card module-card--teal">

          <h2>
            Nueva solicitud
          </h2>

          <p>
            Registra una nueva solicitud de entrega.
          </p>

          <button
            type="button"
            onClick={() =>
              onNavigate('new-order')
            }
          >
            Abrir módulo <ArrowRight size={15} />
          </button>

        </article>


        <article className="module-card module-card--orange">

          <h2>
            Consultar pedidos
          </h2>

          <p>
            Consulta todas tus solicitudes de entrega.
          </p>

          <button
            type="button"
            onClick={() =>
              onNavigate('my-orders')
            }
          >
            Abrir módulo <ArrowRight size={15} />
          </button>

        </article>


        <article className="module-card module-card--blue">

          <h2>
            Configuración
          </h2>

          <p>
            Administra la información de tu comercio.
          </p>

          <button
            type="button"
            onClick={() =>
              onNavigate('commerce')
            }
          >
            Abrir módulo <ArrowRight size={15} />
          </button>

        </article>

      </div>

    </div>
  );
}
