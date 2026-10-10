import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DATA_DIR = path.join(__dirname, '..', 'data');
const OUTBOX_FILE = path.join(DATA_DIR, 'outbox.json');
const STATE_FILE = path.join(DATA_DIR, 'state.json');
const LOG_FILE = path.join(DATA_DIR, 'sync.log');

fs.mkdirSync(DATA_DIR, { recursive: true });

function readJson(file, fallback) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    return fallback;
  }
}
function writeJson(file, obj) {
  fs.writeFileSync(file, JSON.stringify(obj, null, 2));
}

// Outbox: operaciones hechas en local mientras la web estaba caída.
// Cada item: { id, type:'venta'|'replay', sync_uuid?, created_at, attempts, status, ... }
export function loadOutbox() {
  const o = readJson(OUTBOX_FILE, { items: [] });
  return Array.isArray(o.items) ? o.items : [];
}
export function saveOutbox(items) {
  writeJson(OUTBOX_FILE, { items });
}
export function enqueueOp(op) {
  const items = loadOutbox();
  items.push({ id: `op_${Date.now()}_${Math.floor(Math.random() * 1e6)}`, created_at: new Date().toISOString(), attempts: 0, status: 'pending', ...op });
  saveOutbox(items);
  return items[items.length - 1];
}
export function updateOp(id, patch) {
  const items = loadOutbox();
  const i = items.findIndex((x) => x.id === id);
  if (i >= 0) items[i] = { ...items[i], ...patch };
  saveOutbox(items);
}
export function removeOp(id) {
  saveOutbox(loadOutbox().filter((x) => x.id !== id));
}

// Estado persistente del sync (last pull timestamps, modo, conflictos)
export function loadState() {
  return readJson(STATE_FILE, {
    mode: 'unknown', // online_web | offline_web
    lastPushAt: null,
    lastPullAt: null,
    lastPullTs: '2000-01-01 00:00:00',
    webReachable: null,
    localReachable: null,
    conflicts: [],
  });
}
export function saveState(s) {
  writeJson(STATE_FILE, s);
}
export function pushConflict(c) {
  const s = loadState();
  s.conflicts = [c, ...(s.conflicts || [])].slice(0, 200);
  saveState(s);
}

export function log(line) {
  const msg = `[${new Date().toISOString()}] ${line}\n`;
  fs.appendFileSync(LOG_FILE, msg);
  console.log(msg.trim());
}
export function tailLog(n = 200) {
  try {
    const lines = fs.readFileSync(LOG_FILE, 'utf8').trim().split('\n');
    return lines.slice(-n).reverse();
  } catch {
    return [];
  }
}
