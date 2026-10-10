import express from 'express';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { config } from './config.js';
import { loadOutbox, loadState, saveState, tailLog, log, enqueueOp, saveOutbox, serDates } from './store.js';
import { proxy } from './proxy.js';
import { runCycle, refreshHealth, runPush, runPull } from './sync.js';

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
  // Marca un conflicto como revisado (no reintenta solo: el operador decide
  // anular la venta local o ajustar stock y reencolar manualmente).
  const { sync_uuid } = req.body || {};
  const s = loadState();
  s.conflicts = (s.conflicts || []).filter((c) => c.sync_uuid !== sync_uuid);
  saveState(s);
  res.json({ success: true });
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
