import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DATA_DIR = path.join(__dirname, '..', 'data');
const STORE_FILE = path.join(DATA_DIR, 'hub.json');

const MAX = Number(process.env.HUB_MAX_ITEMS) || 50000;

function load() {
  try {
    const o = JSON.parse(fs.readFileSync(STORE_FILE, 'utf8'));
    if (Array.isArray(o.items)) return o;
  } catch { /* primera vez */ }
  return { items: [], devices: {} };
}
function save(o) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
  fs.writeFileSync(STORE_FILE, JSON.stringify(o));
}

// Guarda un lote. Idempotente por uuid: el reintento responde duplicado.
export function pushItems(system, items) {
  const store = load();
  const known = new Set(store.items.map((x) => x.uuid));
  const results = [];
  for (const it of items) {
    const uuid = String(it?.uuid || it?.sync_uuid || '');
    if (uuid.length < 8) {
      results.push({ uuid, status: 'error', message: 'uuid requerido' });
      continue;
    }
    if (known.has(uuid)) {
      results.push({ uuid, status: 'duplicado', message: 'ya existía' });
      continue;
    }
    const entity = String(it.entity || 'pedido');
    const action = String(it.action || 'upsert');
    store.items.push({
      uuid, entity, action,
      system,
      payload: it.payload ?? it,
      ts: it.ts || new Date().toISOString(),
      received_at: new Date().toISOString(),
    });
    known.add(uuid);
    results.push({ uuid, status: 'applied' });
  }
  // Poda FIFO para no crecer sin límite.
  if (store.items.length > MAX) store.items.splice(0, store.items.length - MAX);
  save(store);
  return results;
}

// Pull sin estado en servidor: cada sistema guarda su cursor (received_at/id).
// Por defecto excluye lo que el propio sistema subió (evita eco).
export function pullItems({ since = '2000-01-01 00:00:00', limit = 200, entity = '', exclude_system = '' }) {
  const store = load();
  const lim = Math.max(1, Math.min(500, Number(limit) || 200));
  const rows = store.items
    .filter((x) => x.received_at > since)
    .filter((x) => !entity || x.entity === entity)
    .filter((x) => !exclude_system || x.system !== exclude_system)
    .sort((a, b) => (a.received_at < b.received_at ? -1 : a.received_at > b.received_at ? 1 : 0))
    .slice(0, lim);
  const maxTs = rows.reduce((m, x) => (x.received_at > m ? x.received_at : m), since);
  return { items: rows, max_ts: maxTs };
}

export function touchDevice(system, device = {}) {
  const store = load();
  const id = String(device.id || system);
  store.devices[id] = {
    id,
    system,
    name: String(device.name || '').slice(0, 100),
    last_seen: new Date().toISOString(),
    pushes: (store.devices[id]?.pushes || 0) + (device.pushed || 0),
    pulls: (store.devices[id]?.pulls || 0) + (device.pulled || 0),
  };
  save(store);
  return store.devices[id];
}

export function stats() {
  const store = load();
  const bySystem = {};
  const byEntity = {};
  for (const x of store.items) {
    bySystem[x.system] = (bySystem[x.system] || 0) + 1;
    byEntity[x.entity] = (byEntity[x.entity] || 0) + 1;
  }
  return { total: store.items.length, bySystem, byEntity, devices: Object.keys(store.devices).length };
}
