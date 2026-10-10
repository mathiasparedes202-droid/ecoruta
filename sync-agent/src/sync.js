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

// PUSH: sube pedidos/ventas locales pendientes a la web (idempotente).
// La web responde por item: applied | duplicado | conflicto_pago |
// conflicto_folio_reasignado | conflicto_stock | error
export async function runPush() {
  const s = loadState();
  if (!s.webReachable) return { skipped: 'web no alcanzable' };
  const items = loadOutbox().filter(
    (x) => x.status === 'pending' && (x.type === 'pedido' || x.type === 'venta' || x.type === 'replay_pedido')
  );
  if (!items.length) return { pushed: 0 };

  const pedidos = items.filter((x) => x.type === 'pedido').map((x) => x.snapshot);
  const ventas = items.filter((x) => x.type === 'venta').map((x) => x.snapshot);
  const replays = items
    .filter((x) => x.type === 'replay_pedido')
    .map((x) => ({ sync_uuid: x.sync_uuid, action: x.action, payload: x.payload }));
  log(`[push] subiendo ${pedidos.length} pedido(s) + ${ventas.length} venta(s) + ${replays.length} cambio(s) a la web...`);
  let resp;
  try {
    resp = await webPush({ pedidos, ventas, replays });
  } catch (e) {
    // Fallo de transporte con web alcanzable: transitorio, no se penaliza.
    log(`[push] fallo de red: ${e.message}`);
    return { error: e.message };
  }
  if (!resp?.success) {
    // 401 = SYNC_API_KEY mal o ausente en la web: problema de configuración,
    // no de datos. Se reintenta siempre y se avisa fuerte, sin aparcar la cola.
    if (resp?._http === 401 || /autorizado/i.test(resp?.message || '')) {
      log('[push] 401 No autorizado: revisa SYNC_API_KEY en la web (Render > Environment). La cola se conserva.');
      return { error: 'X-Sync-Key inválido en la web' };
    }
    log(`[push] web rechazó lote: ${JSON.stringify(resp)}`);
    bumpAttempts(items);
    return { error: resp?.message || 'push rechazado' };
  }

  const byUuid = new Map(items.map((x) => [(x.snapshot?.sync_uuid ?? x.sync_uuid) || x.id, x]));
  let applied = 0;
  for (const r of resp.results || []) {
    const op = byUuid.get(r.sync_uuid);
    if (!op) continue;
    const ref = r.id_pedido ? `pedido #${r.id_pedido}` : `folio ${r.numero_factura || '?'}`;
    if (r.status === 'applied' || r.status === 'duplicado') {
      removeOp(op.id);
      applied++;
      log(`[push] ${r.sync_uuid} -> ${r.status} (${ref})`);
    } else if (r.status === 'conflicto_folio_reasignado') {
      // Solo ventas: la web le dio folio nuevo, se corrige el local.
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
    } else if (r.status === 'conflicto_stock' || r.status === 'conflicto_pago') {
      // NO se reintenta a ciegas: queda en conflicto para revisión manual.
      updateOp(op.id, { status: 'conflict', attempts: op.attempts + 1, last_error: r.message });
      pushConflict({ at: new Date().toISOString(), kind: r.status === 'conflicto_pago' ? 'pago' : 'stock', sync_uuid: r.sync_uuid, message: r.message });
      log(`[push] CONFLICTO ${r.status} ${r.sync_uuid}: ${r.message}`);
    } else {
      const n = (op.attempts || 0) + 1;
      // Anti-atasco: tras 5 ciclos fallando con la web alcanzable, el item
      // pasa a conflicto para revisión manual en vez de reintentarse eterno.
      if (n >= 5) {
        updateOp(op.id, { status: 'conflict', attempts: n, last_error: r.message });
        pushConflict({ at: new Date().toISOString(), kind: 'reintentos', sync_uuid: r.sync_uuid, message: r.message });
        log(`[push] ${r.sync_uuid} -> conflicto tras ${n} intentos: ${r.message}`);
      } else {
        updateOp(op.id, { attempts: n, last_error: r.message });
        log(`[push] error ${r.sync_uuid} (intento ${n}): ${r.message}`);
      }
    }
  }
  s.lastPushAt = new Date().toISOString();
  saveState(s);
  return { pushed: applied, total: items.length };
}

// PULL: baja pedidos/ventas nuevos de la web al local (mismo endpoint idempotente del lado local).
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

  const pedidos = resp.data?.pedidos || [];
  const ventas = resp.data?.ventas || [];
  if (pedidos.length || ventas.length) {
    // Reconstruir payload de ventas con detalles para el push local
    const byId = new Map(ventas.map((v) => [v.id_venta, v]));
    const dets = resp.data?.detalles || [];
    const grouped = new Map();
    for (const d of dets) {
      if (!grouped.has(d.id_venta)) grouped.set(d.id_venta, []);
      grouped.get(d.id_venta).push(d);
    }
    const payload = {
      pedidos,
      ventas: [...byId.values()].map((v) => ({ ...v, detalles: grouped.get(v.id_venta) || [] })),
    };
    try {
      const r = await localPush(payload);
      log(`[pull] ${pedidos.length} pedido(s) + ${ventas.length} venta(s) web -> local: ${JSON.stringify((r.results || []).map((x) => x.status))}`);
    } catch (e) {
      log(`[pull] no se pudo aplicar en local: ${e.message}`);
      return { error: e.message };
    }
  }
  s.lastPullAt = new Date().toISOString();
  if (resp.max_ts) s.lastPullTs = resp.max_ts;
  if (resp.server_time && !pedidos.length && !ventas.length) s.lastPullTs = resp.server_time;
  saveState(s);
  return { pulled: pedidos.length + ventas.length };
}

export async function runCycle() {
  await refreshHealth();
  const push = await runPush();
  const pull = await runPull();
  return { push, pull };
}

// Suma un intento a los ops involucrados cuando el lote ni siquiera pudo
// procesarse (red caída a mitad o lote rechazado con web alcanzable).
function bumpAttempts(items) {
  for (const op of items) {
    const n = (op.attempts || 0) + 1;
    if (n >= 5) {
      updateOp(op.id, { status: 'conflict', attempts: n, last_error: 'lote no procesado 5 veces' });
      pushConflict({ at: new Date().toISOString(), kind: 'reintentos', sync_uuid: op.sync_uuid, message: 'El lote no pudo procesarse 5 veces' });
    } else {
      updateOp(op.id, { attempts: n });
    }
  }
}
