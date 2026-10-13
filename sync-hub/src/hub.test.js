// Tests del hub (HTTP real en puerto efímero). Uso: npm test
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
// Estado limpio siempre (el store es un JSON en disco).
fs.rmSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'data'), { recursive: true, force: true });
process.env.HUB_SYSTEMS = 'sys-a:key-a,sys-b:key-b';
const { app } = await import('./server.js');

let n = 0;
const ok = (c, name) => { n++; if (!c) { console.error('FALLO:', name); process.exit(1); } console.log('ok', n, '-', name); };

const srv = app.listen(0);
await new Promise((r) => srv.once('listening', r));
const B = `http://127.0.0.1:${srv.address().port}`;
const H = (k) => ({ 'content-type': 'application/json', ...(k ? { 'x-system-key': k } : {}) });

// 1) sin clave -> 401
let r = await fetch(B + '/hub/push', { method: 'POST', headers: H(), body: '{}' });
ok(r.status === 401, 'sin clave rechaza (401)');

// 2) sys-a sube 2 eventos
const items = [
  { uuid: 'hub-test-uuid-0001', entity: 'pedido', action: 'upsert', payload: { id: 1, total: 15000 } },
  { uuid: 'hub-test-uuid-0002', entity: 'pedido', action: 'upsert', payload: { id: 2, total: 20000 } },
];
r = await fetch(B + '/hub/push', { method: 'POST', headers: H('key-a'), body: JSON.stringify({ items }) });
let j = await r.json();
ok(r.status === 200 && j.results.every((x) => x.status === 'applied'), 'push aplica 2');

// 3) reintento -> duplicado, sin insertar
r = await fetch(B + '/hub/push', { method: 'POST', headers: H('key-a'), body: JSON.stringify({ items }) });
j = await r.json();
ok(j.results.every((x) => x.status === 'duplicado'), 'reintento es duplicado');

// 4) sys-b NO ve lo propio de nadie... ve lo de sys-a (excluye lo suyo: nada suyo hay)
r = await fetch(B + '/hub/pull?since=2000-01-01%2000:00:00', { headers: H('key-b') });
j = await r.json();
ok(j.items.length === 2, 'sys-b pull trae 2 de sys-a');
const cursorB = j.max_ts;

// 5) sys-a NO se ve a sí mismo (anti-eco)
r = await fetch(B + '/hub/pull?since=2000-01-01%2000:00:00', { headers: H('key-a') });
j = await r.json();
ok(j.items.length === 0, 'sys-a no recibe eco propio');

// 6) cursor incremental: nada nuevo desde max_ts
r = await fetch(B + `/hub/pull?since=${encodeURIComponent(cursorB)}`, { headers: H('key-b') });
j = await r.json();
ok(j.items.length === 0, 'cursor no repite');

// 7) stats + devices
r = await fetch(B + '/hub/push', { method: 'POST', headers: H('key-b'), body: JSON.stringify({ items: [], device: { id: 'dev-b', name: 'nodo-b' } }) });
await r.json();
r = await fetch(B + '/hub/stats', { headers: H('key-a') });
j = await r.json();
ok(j.stats.total === 2 && j.stats.devices === 1, 'stats + inventario');

srv.close();
console.log(`\nHUB TESTS OK (${n} asserts)`);
