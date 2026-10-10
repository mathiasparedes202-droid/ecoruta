import { config } from './config.js';
import { loadOutbox, updateOp, removeOp, loadState, saveState, pushConflict, log } from './store.js';
import { checkHealth, webPush, webPull, localPush } from './webClient.js';

// Chequea salud de ambos y actualiza el modo. Devuelve state.
export async function refreshHealth() {
  const s = loadState();
  const [webOk, localOk] = await Promise.all([checkHealth(config.webBase), checkHealth(config.localBase)]);
  s.webReachable = webOk;
  s.localReachable = localOk;
  s.mode = webOk ? 'online_web' : 'offline_web';
  saveState(s);
  return s;
}

// PUSH: sube ventas locales pendientes a la web (idempotente).
// La web responde por item: applied | duplicado | conflicto_folio_reasignado | conflicto_stock | error
export async function runPush() {
  const s = loadState();
  if (!s.webReachable) return { skipped: 'web no alcanzable' };
  const items = loadOutbox().filter((x) => x.status === 'pending' && x.type === 'venta');
  if (!items.length) return { pushed: 0 };

  const ventas = items.map((x) => x.snapshot);
  log(`[push] subiendo ${ventas.length} venta(s) a la web...`);
  let resp;
  try {
    resp = await webPush(ventas);
  } catch (e) {
    log(`[push] fallo de red: ${e.message}`);
    return { error: e.message };
  }
  if (!resp?.success) {
    log(`[push] web rechazó lote: ${JSON.stringify(resp)}`);
    return { error: resp?.message || 'push rechazado' };
  }

  const byUuid = new Map(items.map((x) => [x.snapshot.sync_uuid, x]));
  let applied = 0;
  for (const r of resp.results || []) {
    const op = byUuid.get(r.sync_uuid);
    if (!op) continue;
    if (r.status === 'applied' || r.status === 'duplicado') {
      removeOp(op.id);
      applied++;
      log(`[push] ${r.sync_uuid} -> ${r.status} (folio ${r.numero_factura})`);
    } else if (r.status === 'conflicto_folio_reasignado') {
      // No se duplicó: la web le dio folio nuevo. Se corrige el local vía SQL directo
      // en el próximo pull (la venta web baja con el folio nuevo) y se saca de la cola.
      removeOp(op.id);
      applied++;
      pushConflict({ at: new Date().toISOString(), kind: 'folio_reasignado', sync_uuid: r.sync_uuid, message: r.message });
      log(`[push] ${r.sync_uuid} -> folio reasignado: ${r.message}`);
      try {
        const { localPool } = await import('./localDb.js');
        await localPool().query('UPDATE venta SET numero_factura = ? WHERE sync_uuid = ?', [r.numero_factura, r.sync_uuid]);
      } catch (e) {
        log(`[push] no se pudo corregir folio local: ${e.message}`);
      }
    } else if (r.status === 'conflicto_stock') {
      // NO se reintenta a ciegas: queda en conflicto para revisión manual.
      updateOp(op.id, { status: 'conflict', attempts: op.attempts + 1, last_error: r.message });
      pushConflict({ at: new Date().toISOString(), kind: 'stock', sync_uuid: r.sync_uuid, message: r.message });
      log(`[push] CONFLICTO STOCK ${r.sync_uuid}: ${r.message}`);
    } else {
      updateOp(op.id, { attempts: op.attempts + 1, last_error: r.message });
      log(`[push] error ${r.sync_uuid}: ${r.message}`);
    }
  }
  s.lastPushAt = new Date().toISOString();
  saveState(s);
  return { pushed: applied, total: items.length };
}

// PULL: baja ventas nuevas de la web al local (mismo endpoint idempotente del lado local).
export async function runPull() {
  const s = loadState();
  if (!s.webReachable) return { skipped: 'web no alcanzable' };
  let resp;
  try {
    resp = await webPull(s.lastPullTs || '2000-01-01 00:00:00');
  } catch (e) {
    log(`[pull] fallo de red: ${e.message}`);
    return { error: e.message };
  }
  if (!resp?.success) return { error: resp?.message || 'pull rechazado' };

  const ventas = resp.data?.ventas || [];
  if (ventas.length) {
    // Reconstruir payload con detalles para el push local
    const byId = new Map(ventas.map((v) => [v.id_venta, v]));
    const dets = resp.data?.detalles || [];
    const grouped = new Map();
    for (const d of dets) {
      if (!grouped.has(d.id_venta)) grouped.set(d.id_venta, []);
      grouped.get(d.id_venta).push(d);
    }
    const payload = [...byId.values()].map((v) => ({ ...v, detalles: grouped.get(v.id_venta) || [] }));
    try {
      const r = await localPush(payload);
      log(`[pull] ${ventas.length} venta(s) web -> local: ${JSON.stringify((r.results || []).map((x) => x.status))}`);
    } catch (e) {
      log(`[pull] no se pudo aplicar en local: ${e.message}`);
      return { error: e.message };
    }
  }
  s.lastPullAt = new Date().toISOString();
  if (resp.max_ts) s.lastPullTs = resp.max_ts;
  if (resp.server_time && !ventas.length) s.lastPullTs = resp.server_time;
  saveState(s);
  return { pulled: ventas.length };
}

export async function runCycle() {
  await refreshHealth();
  const push = await runPush();
  const pull = await runPull();
  return { push, pull };
}
