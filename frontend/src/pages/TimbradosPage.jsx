import { useEffect, useState } from 'react';
import {
  fetchTimbrados, fetchTimbradoVigente, createTimbrado, updateTimbrado
} from '../services/facturacionService.js';

// HU-06: gestión del timbrado vigente + alertas (<15 días o <50 folios).
export default function TimbradosPage() {
  const [rows, setRows] = useState([]);
  const [vigente, setVigente] = useState(null);
  const [alerta, setAlerta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [msg, setMsg] = useState('');
  const [form, setForm] = useState({
    ruc: '', razon_social: '', direccion: '', num_timbrado: '',
    establecimiento: '001', punto_expedicion: '001',
    fecha_inicio: '', fecha_fin: '', nro_inicio: 1, nro_fin: 100000, activo: 1
  });

  async function load() {
    setLoading(true);
    setError('');
    try {
      const [lista, vig] = await Promise.all([fetchTimbrados(), fetchTimbradoVigente()]);
      setRows(Array.isArray(lista) ? lista : []);
      setVigente(vig.timbrado || null);
      setAlerta(vig.alerta || null);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  async function guardar() {
    try {
      const r = await createTimbrado({ ...form, nro_inicio: Number(form.nro_inicio), nro_fin: Number(form.nro_fin), activo: Number(form.activo) ? 1 : 0 });
      setMsg(`Timbrado registrado (id ${r.id_timbrado}).`);
      await load();
    } catch (e) {
      setMsg(e.message);
    }
  }

  async function toggle(id, activo) {
    try {
      await updateTimbrado(id, { activo: activo ? 1 : 0 });
      await load();
    } catch (e) {
      setMsg(e.message);
    }
  }

  return (
    <div className="facturas">
      <p className="eyebrow">Facturación legal · SET</p>
      <h1>Timbrados</h1>

      {alerta && (
        <div className={`timbrado-alerta ${alerta.vigente ? '' : 'timbrado-alerta--critica'}`}>
          {alerta.vigente
            ? <>Timbrado vigente{hasta(alerta)} {alerta.mensaje && <><br />⚠ {alerta.mensaje}</>}
              </>
            : <>⛔ {alerta.mensaje}</>}
        </div>
      )}

      {msg && <p className="facturas-msg">{msg}</p>}
      {error && <p className="new-order-error">{error}</p>}

      <h2>Nuevo timbrado</h2>
      <div className="facturas-filtros">
        <label>RUC<input value={form.ruc} onChange={(e) => set('ruc', e.target.value)} placeholder="80000000-1" /></label>
        <label>Razón social<input value={form.razon_social} onChange={(e) => set('razon_social', e.target.value)} placeholder="EcoRuta S.A." /></label>
        <label>N° timbrado<input value={form.num_timbrado} onChange={(e) => set('num_timbrado', e.target.value)} /></label>
        <label>Est.<input value={form.establecimiento} onChange={(e) => set('establecimiento', e.target.value)} size={4} /></label>
        <label>Pto.<input value={form.punto_expedicion} onChange={(e) => set('punto_expedicion', e.target.value)} size={4} /></label>
        <label>Desde<input type="date" value={form.fecha_inicio} onChange={(e) => set('fecha_inicio', e.target.value)} /></label>
        <label>Hasta<input type="date" value={form.fecha_fin} onChange={(e) => set('fecha_fin', e.target.value)} /></label>
        <label>Hasta N°<input type="number" value={form.nro_fin} onChange={(e) => set('nro_fin', e.target.value)} /></label>
        <button type="button" className="primary-action" onClick={guardar}>Registrar</button>
      </div>

      <h2>Historial</h2>
      {loading ? <p>Cargando…</p> : (
        <table className="facturas-tabla">
          <thead><tr><th>Timbrado</th><th>RUC</th><th>Vigencia</th><th>Rango</th><th>Usados</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            {rows.map((t) => (
              <tr key={t.id_timbrado}>
                <td><code>{t.num_timbrado}</code> <small>{t.establecimiento}-{t.punto_expedicion}</small></td>
                <td>{t.ruc}</td>
                <td>{String(t.fecha_inicio).slice(0, 10)} → {String(t.fecha_fin).slice(0, 10)}</td>
                <td>{t.nro_inicio}–{t.nro_fin}</td>
                <td>{t.secuencia_actual}</td>
                <td>{Number(t.activo) && new Date(t.fecha_fin) >= new Date() ? 'Vigente' : 'Inactivo/vencido'}</td>
                <td><button type="button" onClick={() => toggle(t.id_timbrado, !Number(t.activo))}>{Number(t.activo) ? 'Desactivar' : 'Activar'}</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {vigente && <p><small>Folio actual: <code>{vigente.establecimiento}-{vigente.punto_expedicion}-{String(Number(vigente.secuencia_actual) + 1).padStart(7, '0')}</code></small></p>}
    </div>
  );
}

function hasta(alerta) {
  const parts = [];
  if (typeof alerta.dias_restantes === 'number') parts.push(`${alerta.dias_restantes} día(s) restantes`);
  if (typeof alerta.folios_restantes === 'number') parts.push(`${alerta.folios_restantes} folio(s) libres`);
  return parts.length ? ` (${parts.join(' · ')})` : '';
}
