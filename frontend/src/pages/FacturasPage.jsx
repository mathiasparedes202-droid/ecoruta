import { useEffect, useState } from 'react';
import {
  fetchFacturas, fetchFactura, anularFactura, openKude, openFacturaHtml, openFacturaPdf, emitirFacturaPedido
} from '../services/facturacionService.js';
import { formatGs } from '../lib/format.js';

// HU-09: historial con filtros + descarga KuDE + anulación.
// El comercio ve solo sus facturas (id_comercio); el admin ve todo.
export default function FacturasPage({ user }) {
  const isMerchant = Number(user?.id_rol) === 1;
  const commerceId = isMerchant ? Number(user?.id_comercio || user?.id_usuario || 0) : 0;

  const [filtros, setFiltros] = useState({ desde: '', hasta: '', estado: '', pago: '', numero: '' });
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [detail, setDetail] = useState(null);
  const [emitPedido, setEmitPedido] = useState('');
  const [msg, setMsg] = useState('');
  const [enviando, setEnviando] = useState(false);

  async function load(f = filtros) {
    // Validación de filtros: el rango de fechas debe ser coherente.
    if (f.desde && f.hasta && f.desde > f.hasta) {
      setError('La fecha "Desde" no puede ser posterior a "Hasta".');
      setLoading(false);
      return;
    }
    if (f.numero && !/^[0-9\-]{0,20}$/.test(f.numero)) {
      setError('El número de comprobante solo puede contener dígitos y guiones.');
      setLoading(false);
      return;
    }
    setLoading(true);
    setError('');
    try {
      const data = await fetchFacturas({ ...f, ...(commerceId ? { id_comercio: commerceId } : {}) });
      setRows(Array.isArray(data) ? data : []);
    } catch (e) {
      setError(e.message);
      setRows([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function verDetalle(id) {
    try {
      setDetail(await fetchFactura(id));
    } catch (e) {
      setMsg(e.message);
    }
  }

  async function anular(id) {
    const motivo = window.prompt('Motivo de anulación (mínimo 5 caracteres):', '');
    if (motivo === null) return;
    if (motivo.trim().length < 5) {
      setMsg('Indicá el motivo de anulación (mínimo 5 caracteres).');
      return;
    }
    if (enviando) return;
    setEnviando(true);
    try {
      const r = await anularFactura(id, motivo.trim());
      setMsg(r.message);
      setDetail(null);
      await load();
    } catch (e) {
      setMsg(e.message);
    } finally {
      setEnviando(false);
    }
  }

  async function emitir() {
    const raw = String(emitPedido || '').trim();
    // Solo IDs de pedido enteros positivos (evita "abc", "1.5", vacíos).
    if (!/^\d+$/.test(raw) || Number(raw) <= 0) {
      setMsg('Indicá el ID del pedido entregado a facturar (solo números enteros positivos).');
      return;
    }
    if (enviando) return;
    setEnviando(true);
    try {
      const r = await emitirFacturaPedido(Number(raw), {});
      setMsg(`${r.message} Total ₲ ${formatGs(r.total ?? 0)} · IVA ₲ ${formatGs(r.iva_10 ?? 0)}`);
      setEmitPedido('');
      await load();
    } catch (e) {
      setMsg(e.message);
    } finally {
      setEnviando(false);
    }
  }

  const set = (k, v) => setFiltros((f) => ({ ...f, [k]: v }));

  return (
    <div className="facturas">
      <p className="eyebrow">Facturación legal</p>
      <h1>{isMerchant ? 'Mis facturas' : 'Facturas emitidas'}</h1>

      <div className="facturas-filtros">
        <label>Desde <input type="date" value={filtros.desde} onChange={(e) => set('desde', e.target.value)} /></label>
        <label>Hasta <input type="date" value={filtros.hasta} onChange={(e) => set('hasta', e.target.value)} /></label>
        <label>Estado
          <select value={filtros.estado} onChange={(e) => set('estado', e.target.value)}>
            <option value="">Todas</option>
            <option value="Vigente">Vigentes</option>
            <option value="Anulada">Anuladas</option>
          </select>
        </label>
        <label>Pago
          <select value={filtros.pago} onChange={(e) => set('pago', e.target.value)}>
            <option value="">Todos</option>
            <option value="1">Pagados</option>
            <option value="0">Pendientes de pago</option>
          </select>
        </label>
        <label>N° comprobante <input value={filtros.numero} onChange={(e) => set('numero', e.target.value)} placeholder="001-001-…" /></label>
        <button type="button" className="primary-action" onClick={() => load()}>Aplicar</button>
        <button type="button" onClick={() => { const f = { desde: '', hasta: '', estado: '', pago: '', numero: '' }; setFiltros(f); load(f); }}>Limpiar</button>
      </div>

      {!isMerchant && (
        <div className="facturas-emitir">
          <strong>Emitir manual:</strong>
          <input
            value={emitPedido}
            onChange={(e) => setEmitPedido(e.target.value.replace(/[^\d]/g, ''))}
            placeholder="ID pedido entregado"
            inputMode="numeric"
            pattern="[0-9]*"
            aria-label="ID del pedido a facturar"
          />
          <button type="button" className="primary-action" onClick={emitir} disabled={enviando}>
            {enviando ? 'Emitiendo…' : 'Emitir factura'}
          </button>
          <small>Al entregarse, el pedido se factura solo. Esto es para pendientes anteriores.</small>
        </div>
      )}

      {msg && <p className="facturas-msg">{msg}</p>}
      {error && <p className="new-order-error">{error}</p>}

      {loading ? (
        <p>Cargando facturas…</p>
      ) : rows.length === 0 ? (
        <p className="facturas-vacio">
          Aún no hay facturas{isMerchant ? ' para tu comercio' : ''}. Se generan solas al entregar cada pedido
          (con timbrado vigente cargado).
        </p>
      ) : (
        <table className="facturas-tabla">
          <thead>
            <tr><th>N°</th><th>Pedido</th><th>Receptor</th><th>Emisión</th><th>Total</th><th>Estado</th><th>Pago</th><th></th></tr>
          </thead>
          <tbody>
            {rows.map((f) => (
              <tr key={f.id_factura}>
                <td><code>{f.numero_completo}</code></td>
                <td>#{f.id_pedido}</td>
                <td>{f.razon_social_receptor}</td>
                <td>{String(f.fecha_emision || '').slice(0, 16).replace('T', ' ')}</td>
                <td>₲ {formatGs(f.total)}</td>
                <td><span className={`facturas-estado facturas-estado--${f.estado === 'Vigente' ? 'ok' : 'anulada'}`}>{f.estado}</span></td>
                <td><span className={`facturas-estado facturas-estado--${Number(f.pedido_pagado) === 1 ? 'ok' : 'pendiente'}`}>{Number(f.pedido_pagado) === 1 ? 'Pagado' : 'Pendiente'}</span></td>
                <td className="facturas-acciones">
                  <button type="button" onClick={() => verDetalle(f.id_factura)}>Ver</button>
                  <button type="button" onClick={() => openKude(f.id_factura)}>KuDE</button>
                  <button type="button" onClick={() => openFacturaPdf(f.id_factura)}>PDF</button>
                  {f.estado === 'Vigente' && <button type="button" onClick={() => anular(f.id_factura)} disabled={enviando}>Anular</button>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {detail && (
        <div className="auth-backdrop" role="presentation" onMouseDown={(e) => e.target === e.currentTarget && setDetail(null)}>
          <div className="account-modal facturas-detalle">
            <button className="auth-close" type="button" onClick={() => setDetail(null)}>x</button>
            <h2>Factura {detail.numero_completo}</h2>
            <p>Estado: <strong>{detail.estado}</strong> · Pago: <strong>{Number(detail.pedido_pagado) === 1 ? 'Pagado' : 'Pendiente de pago'}</strong> · Emisión: {String(detail.fecha_emision || '').slice(0, 19).replace('T', ' ')}</p>
            <p>Receptor: {detail.razon_social_receptor} — RUC/CI: {detail.ruc_receptor || '—'}</p>
            <table className="facturas-tabla">
              <thead><tr><th>Concepto</th><th>Cant.</th><th>P. unit.</th><th>Subtotal</th></tr></thead>
              <tbody>
                {(detail.items || []).map((it) => (
                  <tr key={it.id_item}>
                    <td>{it.concepto}</td><td>{it.cantidad}</td>
                    <td>₲ {formatGs(it.precio_unitario)}</td><td>₲ {formatGs(it.subtotal)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <p>Total: <strong>₲ {formatGs(detail.total)}</strong> · Gravada 10%: ₲ {formatGs(detail.gravada_10)} · IVA 10%: ₲ {formatGs(detail.iva_10)}</p>
            <p><small>CDC (control interno): {String(detail.cdc || '').slice(0, 24)}…</small></p>
            <div className="facturas-acciones">
              <button type="button" className="primary-action" onClick={() => openKude(detail.id_factura)}>Abrir KuDE / Imprimir</button>
              <button type="button" onClick={() => openFacturaHtml(detail.id_factura)}>Ver HTML</button>
              <button type="button" onClick={() => openFacturaPdf(detail.id_factura)}>Descargar PDF</button>
              {detail.estado === 'Vigente' && <button type="button" onClick={() => anular(detail.id_factura)} disabled={enviando}>Anular</button>}
            </div>
            <h3>Auditoría</h3>
            <ul>
              {(detail.auditoria || []).map((a) => (
                <li key={a.id_auditoria}>{a.fecha} — {a.accion}{a.detalle ? `: ${a.detalle}` : ''}</li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </div>
  );
}
