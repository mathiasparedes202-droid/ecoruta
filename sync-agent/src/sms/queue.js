// Bandeja SMS: paquetes listos para enviar por módem GSM cuando no hay IP.
// Cada item: { id, text, kind, sync_uuid, created_at, status, attempts }
// Transporte futuro: sender AT-commands + inbox hacia /api/sync/push.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SMS_FILE = path.join(__dirname, '..', '..', 'data', 'sms_outbox.json');

function readAll() {
  try {
    const o = JSON.parse(fs.readFileSync(SMS_FILE, 'utf8'));
    return Array.isArray(o.items) ? o.items : [];
  } catch {
    return [];
  }
}
function writeAll(items) {
  fs.mkdirSync(path.dirname(SMS_FILE), { recursive: true });
  fs.writeFileSync(SMS_FILE, JSON.stringify({ items }, null, 2));
}

export function enqueueSms(text, meta = {}) {
  if (typeof text !== 'string' || !text) return null;
  // GSM-7: 160 chars por segmento; el codec ya genera 1 SMS por evento.
  const parts = [];
  let rest = text;
  while (rest.length > 160) {
    parts.push(rest.slice(0, 160));
    rest = rest.slice(160);
  }
  parts.push(rest);
  const items = readAll();
  const base = { id: `sms_${Date.now()}_${Math.floor(Math.random() * 1e6)}`, created_at: new Date().toISOString(), status: 'pending', attempts: 0, segments: parts.length, ...meta };
  for (const p of parts) items.push({ ...base, text: p });
  writeAll(items);
  return base.id;
}

export function listSms() {
  return readAll();
}
