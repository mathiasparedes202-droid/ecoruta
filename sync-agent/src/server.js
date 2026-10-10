import express from 'express';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { config } from './config.js';
import { loadOutbox, loadState, saveState, tailLog, log, enqueueOp, saveOutbox, serDates } from './store.js';
import { proxy } from './proxy.js';
import { runCycle, refreshHealth, runPush, runPull } from './sync.js';
import { webPull, localPush } from './webClient.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const app = express();

// CORS: el navegador (ej. http://localhost:5173) habla directo con el agente,
// así que el preflight OPTIONS debe responderlo el agente, no el PHP de atrás.
// Se refleja el Origin y se permiten credenciales (el frontend usa Bearer + cookies).
app.use((req, res, next) => {
  const origin = req.get('origin');
  res.set('Access-Control-Allow-Origin', origin || '*');
  res.set('Vary', 'Origin');
  res.set('Access-Control-Allow-Credentials', 'true');
  res.set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
  res.set('Access-Control-Allow-Headers', 'Content-Type,Authorization,X-Sync-Key');
  res.set('Access-Control-Max-Age', '86400');
  if (req.method === 'OPTIONS') return res.status(204).end();
  next();
});

app.use(express.json({ limit: '5mb' }));

// Panel local
app.use(express.static(path.join(__dirname, '..', 'public')));

// Proxy intermediario (el frontend apunta aquí)
app.use(proxy);

// API del propio agente (para el panel)
app.get('/agent/status', async (_req, res) => {
  const s = loadState();
  const outbox = loadOutbox();
  res.json({
    mode: s.mode,
    webReachable: s.webReachable,
    localReachable: s.localReachable,
    lastPushAt: s.lastPushAt,
    lastPullAt: s.lastPullTs,
    pending: outbox.filter((x) => x.status === 'pending').length,
    conflicts: (s.conflicts || []).length,
    webBase: config.webBase,
    localBase: config.localBase,
  });
});
app.get('/agent/outbox', (_req, res) => res.json({ items: loadOutbox() }));
app.get('/agent/conflicts', (_req, res) => res.json({ items: loadState().conflicts || [] }));
app.get('/agent/log', (_req, res) => res.json({ lines: tailLog(200) }));
app.post('/agent/sync-now', async (_req, res) => {
  try {
    await refreshHealth();
    const push = await runPush();
    const pull = await runPull();
    res.json({ success: true, push, pull });
  } catch (e) {
    res.status(500).json({ success: false, message: e.message });
  }
});
app.post('/agent/resolve-conflict', (req, res) => {
  // Marca un conflicto como revisado (el operador ya lo resolvió a mano).
  const { sync_uuid } = req.body || {};
  const s = loadState();
  s.conflicts = (s.conflicts || []).filter((c) => c.sync_uuid !== sync_uuid);
  saveState(s);
  res.json({ success: true });
});

// Reintenta un conflicto: lo vuelve a pendiente (ej. un error de formato
// viejo que el servidor ya sabe normalizar). Si vuelve a fallar 5 veces,
// regresa a conflicto solo.
app.post('/agent/retry-conflict', async (req, res) => {
  try {
    const { sync_uuid } = req.body || {};
    const { updateOp } = await import('./store.js');
    const items = loadOutbox();
    const it = items.find((x) => x.sync_uuid === sync_uuid && x.status === 'conflict');
    if (!it) return res.status(404).json({ success: false, message: 'Conflicto no encontrado en la cola' });
    updateOp(it.id, { status: 'pending', attempts: 0, last_error: null });
    log(`[panel] conflicto ${sync_uuid} reencolado para reintento`);
    res.json({ success: true });
  } catch (e) {
    res.status(500).json({ success: false, message: e.message });
  }
});

// Convergencia: encola los pedidos locales cuyo uuid NO está en la web
// (creados directo en local o que nunca subieron). No duplica: la web
// responde `duplicado` si el uuid ya existe allá.
app.post('/agent/sync-missing', async (_req, res) => {
  try {
    const { webPull } = await import('./webClient.js');
    const { localPool } = await import('./localDb.js');
    // Traer todos los uuid de la web (paginado)
    const webUuids = new Set();
    let since = '2000-01-01 00:00:00';
    for (let page = 0; page < 20; page++) {
      const pull = await webPull(since, 500);
      if (!pull?.success) {
        return res.status(502).json({ success: false, message: pull?.message || 'la web no devolvió el pull' });
      }
      for (const p of pull.data?.pedidos || []) {
        if (p.sync_uuid) webUuids.add(p.sync_uuid);
      }
      if ((pull.data?.pedidos || []).length < 500) break;
      since = pull.max_ts || since;
    }
    const [rows] = await localPool().query(
      "SELECT * FROM pedidos WHERE sync_uuid IS NOT NULL AND sync_uuid <> ''"
    );
    const items = loadOutbox();
    let n = 0;
    for (const r of rows) {
      if (webUuids.has(r.sync_uuid)) continue;
      if (items.some((x) => x.sync_uuid === r.sync_uuid && x.status === 'pending')) continue;
      enqueueOp({ type: 'pedido', sync_uuid: r.sync_uuid, snapshot: serDates({ ...r }) });
      n++;
    }
    log(`[sync-missing] ${n} pedido(s) locales faltantes en la web, encolados`);
    res.json({ success: true, faltantes: n, web: webUuids.size, locales: rows.length });
  } catch (e) {
    res.status(500).json({ success: false, message: e.message });
  }
});
// Carga inicial: encola TODOS los pedidos locales sin sync_uuid (histórico
// creado antes del agente) para subirlos a la web en el próximo push.
// Les asigna uuid + origen local. Usar UNA vez; si el mismo pedido real ya
// existe en la web con otro uuid, se duplicará: revisar primero.
app.post('/agent/bootstrap', async (_req, res) => {
  try {
    const { localPool } = await import('./localDb.js');
    const [rows] = await localPool().query(
      "SELECT * FROM pedidos WHERE sync_uuid IS NULL OR sync_uuid = ''"
    );
    let n = 0;
    for (const r of rows) {
      const uuid = randomUUID();
      await localPool().query(
        "UPDATE pedidos SET sync_uuid = ?, origen = 'local' WHERE id_pedido = ?",
        [uuid, r.id_pedido]
      );
      enqueueOp({ type: 'pedido', sync_uuid: uuid, snapshot: serDates({ ...r, sync_uuid: uuid, origen: 'local' }) });
      n++;
    }
    log(`[bootstrap] ${n} pedido(s) histórico(s) encolados para subir a la web`);
    res.json({ success: true, enqueued: n });
  } catch (e) {
    res.status(500).json({ success: false, message: e.message });
  }
});

// ---- Sync por pendrive (nodo remoto sin conectividad) ----
// OUT (remoto -> conectado): exporta la cola pendiente a archivo.
// En la PC conectada se importa y el ciclo normal la sube a la web.
// IN (web -> remoto): se exporta el pull y en destino se aplica en local.
// Todo idempotente por sync_uuid: importar dos veces no duplica.
app.get('/agent/export/outbox', (_req, res) => {
  const items = loadOutbox().filter((x) => x.status === 'pending');
  res.attachment(`ecoruta-outbox-${Date.now()}.json`);
  res.json({ exported_at: new Date().toISOString(), items });
});

app.post('/agent/import/outbox', (req, res) => {
  try {
    const incoming = Array.isArray(req.body?.items) ? req.body.items : null;
    if (!incoming) return res.status(422).json({ success: false, message: 'items debe ser un arreglo' });
    if (incoming.length > 500) return res.status(422).json({ success: false, message: 'máximo 500 ops por archivo' });
    const items = loadOutbox();
    const keyOf = (x) => `${x.type}|${x.action || ''}|${x.sync_uuid || x.id || ''}`;
    const seen = new Set(items.map(keyOf));
    let merged = 0, skipped = 0;
    for (const it of incoming) {
      if (!it || typeof it !== 'object') { skipped++; continue; }
      const k = keyOf(it);
      if (seen.has(k)) { skipped++; continue; }
      seen.add(k);
      items.push({
        id: `op_${Date.now()}_${Math.floor(Math.random() * 1e6)}`,
        created_at: it.created_at || new Date().toISOString(),
        attempts: 0, status: 'pending',
        type: it.type, action: it.action, sync_uuid: it.sync_uuid,
        snapshot: it.snapshot, payload: it.payload, method: it.method, path: it.path, body: it.body,
        imported: true,
      });
      merged++;
    }
    saveOutbox(items);
    log(`[pendrive] importados ${merged} ops a la cola (${skipped} ya estaban)`);
    res.json({ success: true, merged, skipped });
  } catch (e) {
    res.status(500).json({ success: false, message: e.message });
  }
});

app.get('/agent/export/pull', async (req, res) => {
  try {
    // Pagina todo lo nuevo de la web en un solo archivo.
    const all = { pedidos: [], ventas: [], detalles: [] };
    let since = String(req.query.since || '2000-01-01 00:00:00');
    for (let page = 0; page < 20; page++) {
      const pull = await webPull(since, 500);
      if (!pull?.success) {
        return res.status(502).json({ success: false, message: pull?.message || 'la web no devolvió el pull' });
      }
      all.pedidos.push(...(pull.data?.pedidos || []));
      all.ventas.push(...(pull.data?.ventas || []));
      all.detalles.push(...(pull.data?.detalles || []));
      if ((pull.data?.pedidos || []).length < 500 && (pull.data?.ventas || []).length < 500) break;
      since = pull.max_ts || since;
    }
    res.attachment(`ecoruta-pull-${Date.now()}.json`);
    res.json({ exported_at: new Date().toISOString(), data: all });
  } catch (e) {
    res.status(500).json({ success: false, message: e.message });
  }
});

app.post('/agent/import/pull', async (req, res) => {
  try {
    const data = req.body?.data;
    if (!data || typeof data !== 'object') {
      return res.status(422).json({ success: false, message: 'data debe ser el objeto exportado' });
    }
    const pedidos = Array.isArray(data.pedidos) ? data.pedidos : [];
    const ventas = Array.isArray(data.ventas) ? data.ventas : [];
    if (pedidos.length + ventas.length > 1000) {
      return res.status(422).json({ success: false, message: 'máximo 1000 registros por archivo' });
    }
    const resync = (arr) => arr.map((x) => ({ ...x })); // el endpoint local valida e ignora duplicados
    const r = await localPush({ pedidos: resync(pedidos), ventas: resync(ventas), replays: [] });
    const byStatus = {};
    for (const x of r.results || []) byStatus[x.status] = (byStatus[x.status] || 0) + 1;
    log(`[pendrive] pull importado: ${JSON.stringify(byStatus)}`);
    res.json({ success: true, resumen: byStatus });
  } catch (e) {
    res.status(500).json({ success: false, message: e.message });
  }
});

app.listen(config.port, () => {
  log(`[agent] panel + proxy en http://localhost:${config.port} (frontend -> este puerto)`);
  log(`[agent] local=${config.localBase} web=${config.webBase}`);
  upgradeLegacyReplays().catch((e) => log(`[upgrade] ${e.message}`));
});

// Migra replays genéricos viejos ({method, path, body} sin procesador) a
// replay_pedido estructurados cuando el path es una mutación de pedido
// conocida. Idempotente: los ya convertidos no se tocan.
async function upgradeLegacyReplays() {
  const { REPLAY_WRITES } = await import('./proxy.js');
  const { ensurePedidoUuid } = await import('./localDb.js');
  const items = loadOutbox();
  let changed = 0;
  for (const it of items) {
    if (it.type !== 'replay' || !it.path) continue;
    const clean = String(it.path).split('?')[0];
    const hit = REPLAY_WRITES.map((w) => ({ ...w, m: clean.match(w.re) })).find((w) => w.m);
    if (!hit) continue;
    try {
      const uuid = await ensurePedidoUuid(hit.m[1]);
      if (!uuid) continue;
      it.type = 'replay_pedido';
      it.sync_uuid = uuid;
      it.action = hit.action;
      it.payload = it.body || {};
      delete it.method;
      delete it.path;
      delete it.body;
      changed++;
    } catch {
      continue;
    }
  }
  if (changed) {
    saveOutbox(items);
    log(`[upgrade] ${changed} replay(s) genérico(s) convertidos a replay_pedido`);
  }
}

// Ciclo automático
setInterval(async () => {
  try {
    await runCycle();
  } catch (e) {
    log(`[cycle] ${e.message}`);
  }
}, config.pollSeconds * 1000);

// Primera pasada inmediata
runCycle().catch((e) => log(`[cycle] ${e.message}`));
