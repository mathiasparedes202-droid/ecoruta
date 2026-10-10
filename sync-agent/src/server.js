import express from 'express';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { config } from './config.js';
import { loadOutbox, loadState, saveState, tailLog, log } from './store.js';
import { proxy } from './proxy.js';
import { runCycle, refreshHealth, runPush, runPull } from './sync.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const app = express();
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

app.listen(config.port, () => {
  log(`[agent] panel + proxy en http://localhost:${config.port} (frontend -> este puerto)`);
  log(`[agent] local=${config.localBase} web=${config.webBase}`);
});

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
