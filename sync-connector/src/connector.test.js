// Tests del Conector (hub falso en memoria + dirs temporales). Uso: npm test
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import http from 'node:http';

const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'conector-test-'));
process.env.DROP_DIR = path.join(TMP, 'drop');
process.env.INBOX_DIR = path.join(TMP, 'inbox');
process.env.SENT_DIR = path.join(TMP, 'sent');
process.env.CURSOR_FILE = path.join(TMP, 'cursor.txt');
process.env.SYSTEM_KEY = 'k-test';

const C = await import('./connector.js');
const T = await import('./translate.js');

let n = 0;
const ok = (c, name) => { n++; if (!c) { console.error('FALLO:', name); process.exit(1); } console.log('ok', n, '-', name); };

// Hub falso: guarda por uuid, pull con cursor, excluye lo propio por sistema.
const mem = new Map();
let gotCallback = null;
const srv = http.createServer((req, res) => {
  let body = '';
  req.on('data', (d) => (body += d));
  req.on('end', () => {
    const send = (o) => { res.setHeader('content-type', 'application/json'); res.end(JSON.stringify(o)); };
    if (req.url.startsWith('/hub/push') && req.method === 'POST') {
      const items = JSON.parse(body).items || [];
      const results = items.map((it) => {
        if (mem.has(it.uuid)) return { uuid: it.uuid, status: 'duplicado' };
        mem.set(it.uuid, it);
        return { uuid: it.uuid, status: 'applied' };
      });
      return send({ success: true, results });
    }
    if (req.url.startsWith('/hub/pull')) {
      const since = new URL(req.url, 'http://x').searchParams.get('since') || '';
      const items = [...mem.values()];
      return send({ success: true, items, max_ts: '2030-01-01 00:00:00' });
    }
    if (req.url === '/cb' && req.method === 'POST') {
      gotCallback = JSON.parse(body);
      return send({ ok: true });
    }
    res.statusCode = 404;
    return send({});
  });
});
await new Promise((r) => srv.listen(0, r));
process.env.HUB_URL = `http://127.0.0.1:${srv.address().port}`;
// Releer config con el puerto real: las funciones usan config importado...
// (config se evaluó con HUB_URL viejo) -> parche directo:
const { config } = await import('./config.js');
config.hub = process.env.HUB_URL;

// 1) traductor ida y vuelta preserva uuid
const row = { sync_uuid: 'conn-test-uuid-1', id_pedido: 7, tarifa_ecologica: 15000 };
const env1 = T.toHubEnvelope(row, 'pedido');
ok(env1.uuid === 'conn-test-uuid-1', 'envelope usa sync_uuid');
const back = T.toEcorutaPush([{ uuid: env1.uuid, entity: 'pedido', action: 'upsert', payload: row }]);
ok(back.pedidos[0].sync_uuid === 'conn-test-uuid-1', 'vuelta preserva uuid');
ok(T.batchSettled([{ status: 'applied' }, { status: 'duplicado' }]), 'lote asentado');
ok(!T.batchSettled([{ status: 'applied' }, { status: 'error' }]), 'error no asienta');

// 2) drop -> push -> archivado; reintento del mismo archivo no duplica
fs.writeFileSync(path.join(config.dropDir, 'a.json'), JSON.stringify({ items: [env1] }));
let r = await C.runCycle();
ok(r.push.pushed === 1, 'drop subido');
ok(fs.readdirSync(config.dropDir).length === 0 && fs.readdirSync(config.sentDir).length === 1, 'archivado en sent');
// el pull trae lo subido (eco del propio sistema: el hub real lo excluiría;
// el falso no, así se prueba inbox + cursor igual)
r = await C.runCycle();
const inboxFiles = fs.readdirSync(config.inboxDir);
ok(inboxFiles.length >= 1, 'inbox guarda lo bajado');
ok(fs.readFileSync(config.cursorFile, 'utf8').trim() === '2030-01-01 00:00:00', 'cursor avanza');

// 3) callback roto no avanza cursor (backpressure)
config.callback = `http://127.0.0.1:9/cb`;
fs.writeFileSync(config.cursorFile, '2000-01-01 00:00:00');
r = await C.pullToInbox();
ok(r.stopped === 'callback' && fs.readFileSync(config.cursorFile, 'utf8').trim() === '2000-01-01 00:00:00', 'callback roto frena cursor');

srv.close();
fs.rmSync(TMP, { recursive: true, force: true });
console.log(`\nCONECTOR TESTS OK (${n} asserts)`);
