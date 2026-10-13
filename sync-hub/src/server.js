import express from 'express';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import 'dotenv/config';
import { pushItems, pullItems, touchDevice, stats } from './store.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const app = express();
const PORT = Number(process.env.PORT) || 4000;

// Sistemas autorizados desde env: "nombre:clave,nombre:clave"
const SYSTEMS = new Map(
  String(process.env.HUB_SYSTEMS || '')
    .split(',')
    .map((s) => s.trim().split(':'))
    .filter((p) => p.length === 2 && p[0] && p[1])
);

app.use(express.json({ limit: '5mb' }));
app.use(express.static(path.join(__dirname, '..', 'public')));

function auth(req, res, next) {
  const key = req.get('x-system-key') || '';
  for (const [name, secret] of SYSTEMS) {
    if (secret && key === secret) {
      req.system = name;
      return next();
    }
  }
  // Comparación honesta: sin clave válida no hay nada (401, sin pistas).
  res.status(401).json({ success: false, message: 'No autorizado (X-System-Key inválido)' });
}

// Timing-safe-ish: compara longitudes primero para no filtrar por tiempo.
function safeEqual(a, b) {
  if (typeof a !== 'string' || typeof b !== 'string' || a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

app.get('/hub/health', (_req, res) => {
  res.json({ success: true, service: 'ecoruta-sync-hub', server_time: new Date().toISOString(), systems: SYSTEMS.size });
});

// Subir cambios de un sistema. Body: { items: [{uuid, entity, action, payload, ts?}], device? }
app.post('/hub/push', auth, (req, res) => {
  const items = Array.isArray(req.body?.items) ? req.body.items : null;
  if (!items) return res.status(422).json({ success: false, message: 'items debe ser un arreglo' });
  if (items.length > 200) return res.status(422).json({ success: false, message: 'máximo 200 por lote' });
  const results = pushItems(req.system, items);
  if (req.body?.device) {
    touchDevice(req.system, { ...req.body.device, pushed: results.filter((r) => r.status === 'applied').length });
  }
  res.json({ success: true, server_time: new Date().toISOString(), results });
});

// Bajar cambios. ?since= &limit= &entity= — excluye lo propio por defecto.
app.get('/hub/pull', auth, (req, res) => {
  const data = pullItems({
    since: String(req.query.since || '2000-01-01 00:00:00'),
    limit: req.query.limit,
    entity: String(req.query.entity || ''),
    exclude_system: req.query.echo === '1' ? '' : req.system,
  });
  if (req.query.device_id) {
    touchDevice(req.system, { id: String(req.query.device_id), name: String(req.query.device_name || ''), pulled: data.items.length });
  }
  res.json({ success: true, server_time: new Date().toISOString(), ...data });
});

app.get('/hub/stats', auth, (_req, res) => {
  res.json({ success: true, stats: stats() });
});

const isMain = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMain) {
  app.listen(PORT, () => console.log(`[hub] escuchando en :${PORT} (${SYSTEMS.size} sistemas)`));
}

export { app, SYSTEMS, safeEqual };
