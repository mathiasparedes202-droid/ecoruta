import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import {
  Leaf,
  PackageCheck,
  CalendarDays,
  TrendingUp,
  AlertCircle,
  Wallet,
  Footprints,
  Timer,
  PackagePlus,
  Truck,
  CircleCheck,
  Trophy,
  Clock,
  Ban,
  Coins,
  ShoppingBag
} from 'lucide-react';
import { fetchMetrics } from '../services/dashboardService.js';

const fmt = (value) => Number(value || 0).toLocaleString('es-PY');
const fmtKg = (value) => `${fmt(value)} kg`;
const fmtGs = (value) => `₲ ${fmt(Math.round(Number(value || 0)))}`;

const WEEKDAYS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

function formatDateLabel(fecha) {
  const d = new Date(`${fecha}T00:00:00`);
  if (Number.isNaN(d.getTime())) return fecha;
  const day = WEEKDAYS[d.getDay()];
  return `${day} ${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`;
}

const ESTADO_COLORS = {
  pendientes: '#b5563f',
  asignados: '#39715d',
  en_camino: '#2a6fb4',
  entregados: '#2c7a51',
  cancelados: '#9aa7a0'
};

// "{n} min" -> "X d X h X m" (tiempo legible)
function formatMinutes(min) {
  const total = Number(min || 0);
  if (!(total > 0)) return '—';
  const minutos = Math.round(total);
  if (minutos < 60) return `${minutos} min`;
  const d = Math.floor(minutos / 1440);
  const h = Math.floor((minutos % 1440) / 60);
  const m = minutos % 60;
  return `${d > 0 ? `${d} d ` : ''}${h > 0 ? `${h} h ` : ''}${m} min`.trim();
}

// Gráfico de líneas SVG con tooltip al pasar el cursor
function LineChart({ days, series, height = 170 }) {
  const wrapRef = useRef(null);
  const [width, setWidth] = useState(560);
  const [hoverIndex, setHoverIndex] = useState(null);
  const padX = 30;
  const padTop = 14;
  const padBottom = 24;

  useLayoutEffect(() => {
    const el = wrapRef.current;
    if (!el) return;
    const update = () => setWidth(Math.max(220, el.clientWidth));
    update();
    const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(update) : null;
    observer?.observe(el);
    return () => observer?.disconnect();
  }, []);

  const innerW = Math.max(1, width - padX * 2);
  const innerH = Math.max(1, height - padTop - padBottom);
  const seriesMax = Math.max(
    1,
    ...series.flatMap((s) => days.map((d) => Number(d[s.key] || 0)))
  );
  const xFor = (i) => (days.length === 1 ? padX + innerW / 2 : padX + (i / (days.length - 1)) * innerW);
  const yFor = (v) => padTop + innerH - (Number(v || 0) / seriesMax) * innerH;
  const points = (s) => days.map((d, i) => `${xFor(i).toFixed(1)},${yFor(d[s.key]).toFixed(1)}`).join(' ');
  const hitWidth = innerW / days.length;

  return (
    <div className="admin-line" ref={wrapRef}>
      <svg className="admin-line__svg" viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Gráfico de líneas">
        {[0.25, 0.5, 0.75].map((p) => (
          <line
            key={p}
            x1={padX}
            x2={width - padX}
            y1={padTop + innerH * p}
            y2={padTop + innerH * p}
            className="admin-line__grid"
          />
        ))}

        {series.map((s) => (
          <polyline
            key={s.key}
            points={points(s)}
            fill="none"
            stroke={s.color}
            strokeWidth={2.5}
            strokeLinecap="round"
            strokeLinejoin="round"
            className="admin-line__series"
          />
        ))}

        {series.map((s) =>
          days.map((d, i) => (
            <circle
              key={`${s.key}-${i}`}
              cx={xFor(i)}
              cy={yFor(d[s.key])}
              r={hoverIndex === i ? 5 : 3.5}
              fill="#fff"
              stroke={s.color}
              strokeWidth={2}
              className="admin-line__dot"
            />
          ))
        )}

        {days.map((_, i) => (
          <rect
            key={`hit-${i}`}
            x={xFor(i) - hitWidth / 2}
            y={padTop}
            width={hitWidth}
            height={innerH}
            fill="transparent"
            onMouseEnter={() => setHoverIndex(i)}
            onFocus={() => setHoverIndex(i)}
            onMouseLeave={() => setHoverIndex(null)}
            onBlur={() => setHoverIndex(null)}
          />
        ))}

        {days.map((d, i) => (
          <text key={`x-${i}`} x={xFor(i)} y={height - 7} textAnchor="middle" className="admin-line__label">
            {formatDateLabel(d.fecha_reporte)}
          </text>
        ))}
      </svg>

      {hoverIndex !== null && days[hoverIndex] && (
        <div className="admin-line__tooltip" style={{ left: `${Math.min(Math.max(xFor(hoverIndex), 70), width - 70)}px` }}>
          <strong>{days[hoverIndex].fecha_reporte}</strong>
          {series.map((s) => (
            <span key={s.key}>
              <i style={{ background: s.color }}></i>
              {s.label}: <b>{fmt(Number(days[hoverIndex][s.key] || 0))}</b>
            </span>
          ))}
        </div>
      )}
    </div>
  );
}

export default function AdminOverview() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    fetchMetrics()
      .then((response) => {
        if (!active) return;
        const diario = response && typeof response === 'object' && !Array.isArray(response)
          ? (Array.isArray(response.diario) ? response.diario : [])
          : (Array.isArray(response) ? response : []);
        const resumen = response && typeof response === 'object' && !Array.isArray(response)
          ? (response.resumen || null)
          : null;
        setData({ diario, resumen });
        setError('');
      })
      .catch((err) => {
        if (active) setError(err instanceof Error ? err.message : 'No se pudieron cargar las métricas.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => { active = false; };
  }, []);

  const diario = data?.diario || [];
  const resumen = data?.resumen || null;
  const today = diario[0] || null;
  const last7 = diario.slice(0, 7).reverse();

  const totals = useMemo(
    () => {
      if (resumen) {
        return {
          pedidos: Number(resumen.total_pedidos || 0),
          entregados: Number(resumen.entregados || 0),
          co2: Number(resumen.co2_total_ahorrado_kg || 0),
          km: Number(resumen.km_recorridos_sin_emision || 0),
          ingresos: Number(resumen.ingresos_gs || 0),
          cobrado: Number(resumen.ingresos_cobrados || 0)
        };
      }
      return diario.slice(0, 7).reduce(
        (acc, row) => {
          acc.pedidos += Number(row.total_pedidos || 0);
          acc.entregados += Number(row.entregados || 0);
          acc.co2 += Number(row.co2_total_ahorrado_kg || 0);
          acc.km += Number(row.km_recorridos_sin_emision || 0);
          acc.ingresos += Number(row.ingresos_gs || 0);
          acc.cobrado += Number(row.ingresos_cobrados || 0);
          return acc;
        },
        { pedidos: 0, entregados: 0, co2: 0, km: 0, ingresos: 0, cobrado: 0 }
      );
    },
    [resumen, diario]
  );

  // Distribución del estado de hoy (para la dona)
  const todayDist = useMemo(() => {
    if (!today) return null;
    return [
      { key: 'pendientes', label: 'Pendientes', value: Number(today.pendientes || 0), color: ESTADO_COLORS.pendientes },
      { key: 'asignados', label: 'Asignados', value: Number(today.asignados || 0), color: ESTADO_COLORS.asignados },
      { key: 'en_camino', label: 'En camino', value: Number(today.en_camino || 0), color: ESTADO_COLORS.en_camino },
      { key: 'entregados', label: 'Entregados', value: Number(today.entregados || 0), color: ESTADO_COLORS.entregados },
      { key: 'cancelados', label: 'Cancelados', value: Number(today.cancelados || 0), color: ESTADO_COLORS.cancelados }
    ];
  }, [today]);

  const donutData = useMemo(() => {
    if (!todayDist) return null;
    const total = todayDist.reduce((s, d) => s + d.value, 0);
    if (total === 0) return null;
    let acc = 0;
    const stops = todayDist
      .filter((d) => d.value > 0)
      .map((d) => {
        const from = acc;
        acc += (d.value / total) * 100;
        return `${d.color} ${from.toFixed(2)}% ${acc.toFixed(2)}%`;
      });
    return { total, stops, background: `conic-gradient(${stops.join(', ')})` };
  }, [todayDist]);

  const ranking = resumen?.ranking_repartidores || [];

  if (loading) {
    return (
      <section className="operations-panel">
        <p className="eyebrow">PANEL DEL ADMINISTRADOR</p>
        <h2>Cargando métricas del sistema…</h2>
      </section>
    );
  }

  if (error || !today) {
    return (
      <section className="operations-panel">
        <div className="panel-title">
          <div>
            <p className="eyebrow">PANEL DEL ADMINISTRADOR</p>
            <h2>Sin datos disponibles</h2>
          </div>
        </div>
        <div className="admin-overview__error">
          <AlertCircle size={18} />
          <span>{error || 'Aún no hay métricas registradas para mostrar. Espera actividad o revisa la conexión.'}</span>
        </div>
      </section>
    );
  }

  return (
    <section className="operations-panel admin-overview">
      <div className="panel-title">
        <div>
          <p className="eyebrow">PANEL DEL ADMINISTRADOR</p>
          <h2>Resumen de operaciones</h2>
          <p>Entregas, cobros, tiempos de entrega e impacto ambiental del sistema EcoRuta.</p>
        </div>
        <span className="date-filter">
          <CalendarDays size={15} />
          {today.fecha_reporte}
        </span>
      </div>

      {/* KPIs de hoy */}
      <div className="admin-kpis">
        <div className="admin-kpi admin-kpi--deliveries">
          <header><PackageCheck size={19} /><small>ENTREGAS HOY</small></header>
          <strong>{fmt(today.entregados)}</strong>
          <p>de {fmt(today.total_pedidos)} pedidos · <b>{fmt(today.tasa_entregas)}%</b> de cumplimiento</p>
        </div>
        <div className="admin-kpi admin-kpi--co2">
          <header><Leaf size={19} /><small>CO₂ AHORRADO HOY</small></header>
          <strong>{fmtKg(today.co2_total_ahorrado_kg)}</strong>
          <p>emisión evitada con envíos ecológicos</p>
        </div>
        <div className="admin-kpi admin-kpi--km">
          <header><Footprints size={19} /><small>KM SIN EMISIÓN</small></header>
          <strong>{fmt(today.km_recorridos_sin_emision)} km</strong>
          <p>recorridos en bicicleta o vehículo eléctrico</p>
        </div>
        <div className="admin-kpi admin-kpi--income">
          <header><Wallet size={19} /><small>INGRESOS DE HOY</small></header>
          <strong>{fmtGs(today.ingresos_gs)}</strong>
          <p>tarifas ecológicas de las entregas del día</p>
        </div>
      </div>

      {/* KPIs de cobros y tiempos de hoy */}
      <div className="admin-kpis">
        <div className="admin-kpi admin-kpi--cash">
          <header><ShoppingBag size={19} /><small>COBRADO HOY</small></header>
          <strong>{fmtGs(today.ingresos_cobrados)}</strong>
          <p>dinero efectivamente cobrado</p>
        </div>
        <div className="admin-kpi admin-kpi--pending">
          <header><Coins size={19} /><small>PENDIENTE DE COBRO HOY</small></header>
          <strong>{fmtGs(today.ingresos_pendientes)}</strong>
          <p>entregas del día que aún no se cobran</p>
        </div>
        <div className="admin-kpi admin-kpi--time">
          <header><Clock size={19} /><small>TIEMPO PROMEDIO</small></header>
          <strong>{formatMinutes(today.tiempo_promedio_entrega_min)}</strong>
          <p>entre la solicitud y la entrega</p>
        </div>
        <div className="admin-kpi admin-kpi--cancel">
          <header><Ban size={19} /><small>CANCELADOS HOY</small></header>
          <strong>{fmt(today.cancelados)}</strong>
          <p>pedidos cancelados en el día</p>
        </div>
      </div>

      {/* Resumen 7 días */}
      <div className="admin-weekly">
        <div className="admin-weekly__card">
          <span><PackageCheck size={17} /> Entregas 7 días</span>
          <strong>{fmt(totals.entregados)}</strong>
          <small>de {fmt(totals.pedidos)} pedidos</small>
        </div>
        <div className="admin-weekly__card">
          <span><Leaf size={17} /> CO₂ ahorrado</span>
          <strong>{fmtKg(totals.co2)}</strong>
          <small>en los últimos 7 días</small>
        </div>
        <div className="admin-weekly__card">
          <span><ShoppingBag size={17} /> Cobrado</span>
          <strong>{fmtGs(totals.cobrado)}</strong>
          <small>falta cobrar {fmtGs(resumen?.ingresos_pendientes || 0)}</small>
        </div>
        <div className="admin-weekly__card">
          <span><Wallet size={17} /> Ingresos acumulados</span>
          <strong>{fmtGs(totals.ingresos)}</strong>
          <small>últimos 7 días</small>
        </div>
      </div>

      {/* Gráficos */}
      <div className="admin-charts">
        <div className="admin-chart-card">
          <header>
            <h3><TrendingUp size={16} /> Entregas por día</h3>
            <span>últimos 7 días</span>
          </header>
          <div className="admin-chart-legend">
            <span><i className="admin-chart-legend__dot" style={{ background: ESTADO_COLORS.entregados }}></i>Entregadas</span>
            <span><i className="admin-chart-legend__dot" style={{ background: ESTADO_COLORS.cancelados }}></i>Canceladas</span>
          </div>
          <LineChart
            days={last7}
            series={[
              { key: 'entregados', label: 'Entregadas', color: ESTADO_COLORS.entregados },
              { key: 'cancelados', label: 'Canceladas', color: ESTADO_COLORS.cancelados }
            ]}
          />
        </div>

        <div className="admin-chart-card">
          <header>
            <h3><CircleCheck size={16} /> Estado de los pedidos hoy</h3>
            <span>{fmt(today.total_pedidos)} en total</span>
          </header>
          {donutData ? (
            <div className="admin-donut">
              <div className="admin-donut__ring" style={{ background: donutData.background }}>
                <div className="admin-donut__center">
                  <strong>{fmt(donutData.total)}</strong>
                  <small>pedidos</small>
                </div>
              </div>
              <div className="admin-donut__legend">
                {todayDist.map((d) => (
                  <div className="admin-donut__item" key={d.key}>
                    <i style={{ background: d.color }}></i>
                    <span>{d.label}</span>
                    <strong>{fmt(d.value)}</strong>
                  </div>
                ))}
              </div>
            </div>
          ) : (
            <div className="admin-donut__empty">
              <Timer size={22} />
              <p>Sin movimiento todavía hoy.</p>
            </div>
          )}
        </div>

        <div className="admin-chart-card">
          <header>
            <h3><Wallet size={16} /> Ingresos por día</h3>
            <span>cobrado vs. pendiente</span>
          </header>
          <div className="admin-chart-legend">
            <span><i className="admin-chart-legend__dot admin-chart-legend__dot--cobrado"></i>Cobrado</span>
            <span><i className="admin-chart-legend__dot admin-chart-legend__dot--pend"></i>Pendiente</span>
          </div>
          <LineChart
            days={last7}
            series={[
              { key: 'ingresos_cobrados', label: 'Cobrado', color: '#28604f' },
              { key: 'ingresos_pendientes', label: 'Pendiente', color: '#d9a441' }
            ]}
          />
        </div>

        <div className="admin-chart-card">
          <header>
            <h3><Trophy size={16} /> Ranking de repartidores</h3>
            <span>entregas · últimos 7 días</span>
          </header>
          {ranking.length > 0 ? (
            <div className="admin-ranking">
              {ranking.map((r) => (
                <div className="admin-ranking__row" key={r.nombre_completo}>
                  <span className={`admin-ranking__pos admin-ranking__pos--${r.posicion}`}>{r.posicion}</span>
                  <div className="admin-ranking__main">
                    <strong>{r.nombre_completo}</strong>
                    <small>{fmt(r.km)} km · {fmtKg(r.co2)} de CO₂ ahorrado</small>
                  </div>
                  <span className="admin-ranking__del"><b>{fmt(r.entregas)}</b> entregas</span>
                </div>
              ))}
            </div>
          ) : (
            <div className="admin-donut__empty">
              <Truck size={22} />
              <p>Aún no hay entregas registradas por repartidores.</p>
            </div>
          )}
        </div>
      </div>

      {/* Tabla de actividad */}
      {last7.length > 0 && (
        <div className="admin-table-card">
          <header>
            <h3><CalendarDays size={16} /> Actividad diaria reciente</h3>
            <span>últimos 7 días</span>
          </header>
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Pendientes</th>
                  <th>En curso</th>
                  <th>Entregados</th>
                  <th>Cancelados</th>
                  <th>Cumplimiento</th>
                  <th>Tiempo prom.</th>
                  <th>Cobrado</th>
                  <th>Ingresos</th>
                </tr>
              </thead>
              <tbody>
                {last7.map((row) => (
                  <tr key={row.fecha_reporte}>
                    <td><b>{row.fecha_reporte}</b></td>
                    <td>{fmt(row.pendientes)}
                      <i className="admin-table__icon admin-table__icon--pend"><PackagePlus size={12} /></i>
                    </td>
                    <td>{fmt(row.en_curso)}
                      <i className="admin-table__icon admin-table__icon--camino"><Truck size={12} /></i>
                    </td>
                    <td className="admin-table__ok">{fmt(row.entregados)}</td>
                    <td className="admin-table__cancel">{fmt(row.cancelados)}</td>
                    <td><span className="admin-table__rate">{fmt(row.tasa_entregas)}%</span></td>
                    <td>{formatMinutes(row.tiempo_promedio_entrega_min)}</td>
                    <td className="admin-table__money">{fmtGs(row.ingresos_cobrados)}</td>
                    <td className="admin-table__money">{fmtGs(row.ingresos_gs)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </section>
  );
}