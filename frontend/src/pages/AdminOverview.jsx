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
function LineChart({ days, series, height = 170, showArea = false }) {
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
    <div className="admin-line" ref={wrapRef} style={{ height }}>
      <svg className="admin-line__svg" style={{ height }} viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Gráfico de líneas">
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
          showArea && (
            <polygon
              key={`${s.key}-area`}
              points={`${padX},${padTop + innerH} ${points(s)} ${width - padX},${padTop + innerH}`}
              fill={s.color}
              fillOpacity=".12"
            />
          )
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

        {[0, 0.5, 1].map((p) => (
          <text
            key={`y-${p}`}
            x={4}
            y={padTop + innerH - innerH * p + 3}
            className="admin-line__axis"
          >
            {Math.round(seriesMax * p)}
          </text>
        ))}

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

function toISODateLocal(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function parseResponse(response) {
  const diario = response && typeof response === 'object' && !Array.isArray(response)
    ? (Array.isArray(response.diario) ? response.diario : [])
    : (Array.isArray(response) ? response : []);
  const resumen = response && typeof response === 'object' && !Array.isArray(response)
    ? (response.resumen || null)
    : null;
  const rango = (response && response.rango) || (resumen && resumen.desde
    ? { desde: resumen.desde, hasta: resumen.hasta }
    : null);
  return { diario, resumen, rango };
}

export default function AdminOverview() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filtering, setFiltering] = useState(false);
  const [error, setError] = useState('');
  const [desdeInput, setDesdeInput] = useState('');
  const [hastaInput, setHastaInput] = useState('');
  const [applied, setApplied] = useState({ desde: '', hasta: '' });

  const loadMetrics = async (desde, hasta, initial = false) => {
    if (initial) setLoading(true);
    else setFiltering(true);
    setError('');
    try {
      const response = await fetchMetrics(desde || undefined, hasta || undefined);
      const { diario, resumen, rango } = parseResponse(response);
      setData({ diario, resumen, rango });
      const rDesde = rango?.desde || desde || '';
      const rHasta = rango?.hasta || hasta || '';
      setApplied({ desde: rDesde, hasta: rHasta });
      // Inicializar los inputs con el rango devuelto (solo si están vacíos
      // o si es la carga inicial).
      if (initial) {
        if (rDesde) setDesdeInput(rDesde);
        if (rHasta) setHastaInput(rHasta);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudieron cargar las métricas.');
    } finally {
      if (initial) setLoading(false);
      else setFiltering(false);
    }
  };

  useEffect(() => {
    let active = true;
    (async () => {
      if (!active) return;
      await loadMetrics('', '', true);
    })();
    return () => { active = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleApply = () => {
    let desde = desdeInput || '';
    let hasta = hastaInput || '';
    if (desde && hasta && desde > hasta) {
      [desde, hasta] = [hasta, desde];
      setDesdeInput(desde);
      setHastaInput(hasta);
    }
    loadMetrics(desde, hasta, false);
  };

  const handleClear = async () => {
    // Volver a los últimos 7 días (comportamiento por defecto del backend).
    const today = new Date();
    const weekAgo = new Date(today);
    weekAgo.setDate(today.getDate() - 6);
    const d = toISODateLocal(weekAgo);
    const h = toISODateLocal(today);
    setDesdeInput(d);
    setHastaInput(h);
    await loadMetrics(d, h, false);
  };

  const applyPreset = (kind) => {
    const today = new Date();
    let desde = '';
    let hasta = toISODateLocal(today);
    if (kind === 'hoy') {
      desde = hasta;
    } else if (kind === '7') {
      const d = new Date(today);
      d.setDate(today.getDate() - 6);
      desde = toISODateLocal(d);
    } else if (kind === '30') {
      const d = new Date(today);
      d.setDate(today.getDate() - 29);
      desde = toISODateLocal(d);
    } else if (kind === 'mes') {
      desde = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;
    }
    setDesdeInput(desde);
    setHastaInput(hasta);
    loadMetrics(desde, hasta, false);
  };

  const diario = data?.diario || [];
  const resumen = data?.resumen || null;
  const today = diario[0] || null;
  // Días en orden ascendente para los gráficos de línea.
  const chartDays = useMemo(() => [...diario].reverse(), [diario]);
  const isSingleDay = diario.length === 1;

  // Agregado del periodo filtrado (los KPIs superiores lo usan cuando
  // el rango tiene más de un día; con un solo día muestran ese día).
  const periodo = useMemo(() => {
    const acc = diario.reduce(
      (a, row) => {
        a.pedidos += Number(row.total_pedidos || 0);
        a.entregados += Number(row.entregados || 0);
        a.pendientes += Number(row.pendientes || 0);
        a.asignados += Number(row.asignados || 0);
        a.enCamino += Number(row.en_camino || 0);
        a.cancelados += Number(row.cancelados || 0);
        a.co2 += Number(row.co2_total_ahorrado_kg || 0);
        a.km += Number(row.km_recorridos_sin_emision || 0);
        a.ingresos += Number(row.ingresos_gs || 0);
        a.cobrado += Number(row.ingresos_cobrados || 0);
        a.pendienteCobro += Number(row.ingresos_pendientes || 0);
        return a;
      },
      { pedidos: 0, entregados: 0, pendientes: 0, asignados: 0, enCamino: 0, cancelados: 0, co2: 0, km: 0, ingresos: 0, cobrado: 0, pendienteCobro: 0 }
    );
    acc.tasa = acc.pedidos > 0 ? Math.round((acc.entregados / acc.pedidos) * 1000) / 10 : 0;
    acc.tiempo = Number(resumen?.tiempo_promedio_entrega_min || 0);
    return acc;
  }, [diario, resumen]);

  const totals = useMemo(
    () => {
      if (resumen) {
        return {
          pedidos: Number(resumen.total_pedidos || periodo.pedidos || 0),
          entregados: Number(resumen.entregados || periodo.entregados || 0),
          co2: Number(resumen.co2_total_ahorrado_kg || periodo.co2 || 0),
          km: Number(resumen.km_recorridos_sin_emision || periodo.km || 0),
          ingresos: Number(resumen.ingresos_gs || periodo.ingresos || 0),
          cobrado: Number(resumen.ingresos_cobrados || periodo.cobrado || 0)
        };
      }
      return {
        pedidos: periodo.pedidos,
        entregados: periodo.entregados,
        co2: periodo.co2,
        km: periodo.km,
        ingresos: periodo.ingresos,
        cobrado: periodo.cobrado
      };
    },
    [resumen, periodo]
  );

  // Valores que muestran las tarjetas principales: día único o periodo.
  const kpi = useMemo(() => {
    if (isSingleDay && today) {
      return {
        entregados: Number(today.entregados || 0),
        pedidos: Number(today.total_pedidos || 0),
        tasa: Number(today.tasa_entregas || 0),
        co2: Number(today.co2_total_ahorrado_kg || 0),
        km: Number(today.km_recorridos_sin_emision || 0),
        ingresos: Number(today.ingresos_gs || 0),
        cobrado: Number(today.ingresos_cobrados || 0),
        pendiente: Number(today.ingresos_pendientes || 0),
        tiempo: Number(today.tiempo_promedio_entrega_min || 0),
        cancelados: Number(today.cancelados || 0)
      };
    }
    return {
      entregados: periodo.entregados,
      pedidos: periodo.pedidos,
      tasa: periodo.tasa,
      co2: periodo.co2,
      km: periodo.km,
      ingresos: periodo.ingresos,
      cobrado: periodo.cobrado,
      pendiente: periodo.pendienteCobro,
      tiempo: periodo.tiempo,
      cancelados: periodo.cancelados
    };
  }, [isSingleDay, today, periodo]);

  const kpiSuffix = isSingleDay ? 'HOY' : 'EN EL PERIODO';

  const rangeLabel = diario.length > 0
    ? `${diario[diario.length - 1].fecha_reporte} → ${diario[0].fecha_reporte} · ${diario.length} día${diario.length === 1 ? '' : 's'}`
    : (applied.desde || applied.hasta ? `${applied.desde || '…'} → ${applied.hasta || '…'}` : '');

  // Distribución del estado en el periodo (para la dona)
  const todayDist = useMemo(() => {
    if (diario.length === 0) return null;
    if (isSingleDay && today) {
      return [
        { key: 'pendientes', label: 'Pendientes', value: Number(today.pendientes || 0), color: ESTADO_COLORS.pendientes },
        { key: 'asignados', label: 'Asignados', value: Number(today.asignados || 0), color: ESTADO_COLORS.asignados },
        { key: 'en_camino', label: 'En camino', value: Number(today.en_camino || 0), color: ESTADO_COLORS.en_camino },
        { key: 'entregados', label: 'Entregados', value: Number(today.entregados || 0), color: ESTADO_COLORS.entregados },
        { key: 'cancelados', label: 'Cancelados', value: Number(today.cancelados || 0), color: ESTADO_COLORS.cancelados }
      ];
    }
    return [
      { key: 'pendientes', label: 'Pendientes', value: periodo.pendientes, color: ESTADO_COLORS.pendientes },
      { key: 'asignados', label: 'Asignados', value: periodo.asignados, color: ESTADO_COLORS.asignados },
      { key: 'en_camino', label: 'En camino', value: periodo.enCamino, color: ESTADO_COLORS.en_camino },
      { key: 'entregados', label: 'Entregados', value: periodo.entregados, color: ESTADO_COLORS.entregados },
      { key: 'cancelados', label: 'Cancelados', value: periodo.cancelados, color: ESTADO_COLORS.cancelados }
    ];
  }, [diario.length, isSingleDay, today, periodo]);

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

  if (error && diario.length === 0 && !data) {
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
      </div>

      {/* Filtro por fechas */}
      <div className="admin-filters">
        <div className="admin-filters__row">
          <label className="date-filter">
            <CalendarDays size={15} />
            <span>Desde</span>
            <input
              type="date"
              value={desdeInput}
              max={hastaInput || undefined}
              onChange={(e) => setDesdeInput(e.target.value)}
              aria-label="Fecha desde"
            />
          </label>
          <label className="date-filter">
            <span>Hasta</span>
            <input
              type="date"
              value={hastaInput}
              min={desdeInput || undefined}
              onChange={(e) => setHastaInput(e.target.value)}
              aria-label="Fecha hasta"
            />
          </label>
          <button
            type="button"
            className="admin-filters__apply"
            onClick={handleApply}
            disabled={filtering || loading}
          >
            {filtering ? 'Filtrando…' : 'Aplicar'}
          </button>
          <button
            type="button"
            className="admin-filters__clear"
            onClick={handleClear}
            disabled={filtering || loading}
          >
            Limpiar
          </button>
        </div>
        <div className="admin-filters__presets">
          <button type="button" onClick={() => applyPreset('hoy')} disabled={filtering}>Hoy</button>
          <button type="button" onClick={() => applyPreset('7')} disabled={filtering}>Últimos 7 días</button>
          <button type="button" onClick={() => applyPreset('30')} disabled={filtering}>Últimos 30 días</button>
          <button type="button" onClick={() => applyPreset('mes')} disabled={filtering}>Este mes</button>
        </div>
        {rangeLabel && <small className="admin-filters__range">{rangeLabel}{error ? ` · ${error}` : ''}</small>}
      </div>

      {/* KPIs del periodo filtrado */}
      <div className={`admin-kpis${filtering ? ' admin-kpis--loading' : ''}`}>
        <div className="admin-kpi admin-kpi--deliveries">
          <header><PackageCheck size={19} /><small>ENTREGAS {kpiSuffix}</small></header>
          <strong>{fmt(kpi.entregados)}</strong>
          <p>de {fmt(kpi.pedidos)} pedidos · <b>{fmt(kpi.tasa)}%</b> de cumplimiento</p>
        </div>
        <div className="admin-kpi admin-kpi--co2">
          <header><Leaf size={19} /><small>CO₂ AHORRADO {kpiSuffix}</small></header>
          <strong>{fmtKg(kpi.co2)}</strong>
          <p>emisión evitada con envíos ecológicos</p>
        </div>
        <div className="admin-kpi admin-kpi--km">
          <header><Footprints size={19} /><small>KM SIN EMISIÓN</small></header>
          <strong>{fmt(kpi.km)} km</strong>
          <p>recorridos en bicicleta o vehículo eléctrico</p>
        </div>
        <div className="admin-kpi admin-kpi--income">
          <header><Wallet size={19} /><small>INGRESOS {kpiSuffix}</small></header>
          <strong>{fmtGs(kpi.ingresos)}</strong>
          <p>tarifas ecológicas de las entregas del periodo</p>
        </div>
      </div>

      {/* KPIs de cobros y tiempos del periodo */}
      <div className="admin-kpis">
        <div className="admin-kpi admin-kpi--cash">
          <header><ShoppingBag size={19} /><small>COBRADO {kpiSuffix}</small></header>
          <strong>{fmtGs(kpi.cobrado)}</strong>
          <p>dinero efectivamente cobrado</p>
        </div>
        <div className="admin-kpi admin-kpi--pending">
          <header><Coins size={19} /><small>PENDIENTE DE COBRO {kpiSuffix}</small></header>
          <strong>{fmtGs(kpi.pendiente)}</strong>
          <p>entregas del periodo que aún no se cobran</p>
        </div>
        <div className="admin-kpi admin-kpi--time">
          <header><Clock size={19} /><small>TIEMPO PROMEDIO</small></header>
          <strong>{formatMinutes(kpi.tiempo)}</strong>
          <p>entre la solicitud y la entrega</p>
        </div>
        <div className="admin-kpi admin-kpi--cancel">
          <header><Ban size={19} /><small>CANCELADOS {kpiSuffix}</small></header>
          <strong>{fmt(kpi.cancelados)}</strong>
          <p>pedidos cancelados en el periodo</p>
        </div>
      </div>

      {/* Resumen del periodo filtrado */}
      <div className="admin-weekly">
        <div className="admin-weekly__card">
          <span><PackageCheck size={17} /> Entregas {diario.length} días</span>
          <strong>{fmt(totals.entregados)}</strong>
          <small>de {fmt(totals.pedidos)} pedidos</small>
        </div>
        <div className="admin-weekly__card">
          <span><Leaf size={17} /> CO₂ ahorrado</span>
          <strong>{fmtKg(totals.co2)}</strong>
          <small>en el periodo seleccionado</small>
        </div>
        <div className="admin-weekly__card">
          <span><ShoppingBag size={17} /> Cobrado</span>
          <strong>{fmtGs(totals.cobrado)}</strong>
          <small>falta cobrar {fmtGs(resumen?.ingresos_pendientes || periodo.pendienteCobro || 0)}</small>
        </div>
        <div className="admin-weekly__card">
          <span><Wallet size={17} /> Ingresos acumulados</span>
          <strong>{fmtGs(totals.ingresos)}</strong>
          <small>{rangeLabel || 'periodo seleccionado'}</small>
        </div>
      </div>

      {/* Gráficos */}
      <div className="admin-charts">
        <div className="admin-chart-card admin-chart-card--deliveries">
          <header>
            <h3><TrendingUp size={16} /> Entregas por día</h3>
            <span>{rangeLabel || 'periodo seleccionado'}</span>
          </header>
          <div className="admin-chart-legend">
            <span><i className="admin-chart-legend__dot" style={{ background: ESTADO_COLORS.entregados }}></i>Entregadas</span>
            <span><i className="admin-chart-legend__dot" style={{ background: ESTADO_COLORS.en_camino }}></i>En camino</span>
            <span><i className="admin-chart-legend__dot" style={{ background: ESTADO_COLORS.cancelados }}></i>Canceladas</span>
          </div>
          <LineChart
            days={chartDays}
            height={220}
            showArea
            series={[
              { key: 'entregados', label: 'Entregadas', color: ESTADO_COLORS.entregados },
              { key: 'en_camino', label: 'En camino', color: ESTADO_COLORS.en_camino },
              { key: 'cancelados', label: 'Canceladas', color: ESTADO_COLORS.cancelados }
            ]}
          />
        </div>

        <div className="admin-chart-card admin-chart-card--status">
          <header>
            <h3><CircleCheck size={16} /> Estado de los pedidos {isSingleDay ? 'hoy' : 'en el periodo'}</h3>
            <span>{fmt(periodo.pedidos)} en total</span>
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
              <p>Sin movimiento en el periodo seleccionado.</p>
            </div>
          )}
        </div>

        <div className="admin-chart-card admin-chart-card--income">
          <header>
            <h3><Wallet size={16} /> Ingresos por día</h3>
            <span>cobrado vs. pendiente</span>
          </header>
          <div className="admin-chart-legend">
            <span><i className="admin-chart-legend__dot admin-chart-legend__dot--cobrado"></i>Cobrado</span>
            <span><i className="admin-chart-legend__dot admin-chart-legend__dot--pend"></i>Pendiente</span>
          </div>
          <LineChart
            days={chartDays}
            series={[
              { key: 'ingresos_cobrados', label: 'Cobrado', color: '#28604f' },
              { key: 'ingresos_pendientes', label: 'Pendiente', color: '#d9a441' }
            ]}
          />
        </div>

        <div className="admin-chart-card">
          <header>
            <h3><Trophy size={16} /> Ranking de repartidores</h3>
            <span>entregas · {rangeLabel || 'periodo'}</span>
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
      {diario.length > 0 && (
        <div className="admin-table-card">
          <header>
            <h3><CalendarDays size={16} /> Actividad diaria</h3>
            <span>{rangeLabel || 'periodo seleccionado'}</span>
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
                {diario.map((row) => (
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