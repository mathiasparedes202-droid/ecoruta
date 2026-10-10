import { Router, json } from 'express';
import { randomUUID } from 'node:crypto';
import { config } from './config.js';
import { loadState } from './store.js';
import { forward } from './webClient.js';
import { enqueueOp, log, serDates, loadOutbox, saveOutbox } from './store.js';
import { fetchLocalPedido, fetchLocalVenta, ensurePedidoUuid } from './localDb.js';

export const proxy = Router();
proxy.use(json({ limit: '5mb' }));

// Escrituras con snapshot idempotente: el pedido/venta creado offline se
// guarda en local con sync_uuid y se encola para el push posterior.
const SNAPSHOT_WRITES = [
  {
    test: (m, p) => m === 'POST' && /^\/api\/orders\/?$/.test(p),
    type: 'pedido',
    fetch: fetchLocalPedido,
    pickId: (j) => j?.id_pedido,
  },
  {
    test: (m, p) => m === 'POST' && /^\/api\/ventas\/?$/.test(p),
    type: 'venta',
    fetch: fetchLocalVenta,
    pickId: (j) => j?.data?.venta?.id_venta || j?.data?.venta?.venta?.id_venta || j?.id_venta,
  },
];

// Cambios sobre pedidos existentes hechos offline (pago, estado, etc.):
// se aplican en local y se encolan como replay estructurado {sync_uuid, action}
// para re-aplicarlos en la web al volver (búsqueda por uuid, sin duplicar).
export const REPLAY_WRITES = [
  { re: /^\/api\/orders\/(\d+)\/pago\/?$/, action: 'pago' },
  { re: /^\/api\/orders\/(\d+)\/status\/?$/, action: 'estado' },
  { re: /^\/api\/orders\/(\d+)\/assign\/?$/, action: 'assign' },
  { re: /^\/api\/orders\/(\d+)\/cancel\/?$/, action: 'cancel' },
  { re: /^\/api\/orders\/(\d+)\/?$/, action: 'editar' },
];

// - GET: web si online, local si offline.
// - POST /api/orders (y /api/ventas): web si online; si offline -> local + outbox.
// - Resto de escrituras offline: local + replay genérico.
proxy.use('/api', async (req, res) => {
  const s = loadState();
  const online = s.mode === 'online_web' || s.webReachable !== false;
  const isRead = ['GET', 'HEAD'].includes(req.method);
  const path = req.originalUrl.split('?')[0];
  const snap = SNAPSHOT_WRITES.find((w) => w.test(req.method, path));
  const replayMatch = !isRead && !snap
    ? REPLAY_WRITES.map((w) => ({ ...w, m: req.method === 'PATCH' || req.method === 'PUT' ? path.match(w.re) : null })).find((w) => w.m)
    : null;

  try {
    if (isRead) {
      const dest = online ? config.webBase : config.localBase;
      const key = online ? config.webSyncKey : config.localSyncKey;
      const r = await forward(dest, req, { 'x-sync-key': key });
      res.status(r.status);
      if (r.json) return res.json(r.json);
      res.set('content-type', r.contentType || 'text/html');
      return res.send(r.text);
    }

    if (snap) {
      // Asegurar sync_uuid (idempotencia) antes de guardar en cualquier lado.
      req.body = req.body || {};
      if (!req.body.sync_uuid) req.body.sync_uuid = randomUUID();
      req.body.origen = online ? 'web' : 'local';

      const dest = online ? config.webBase : config.localBase;
      const key = online ? config.webSyncKey : config.localSyncKey;
      const r = await forward(dest, req, { 'x-sync-key': key });

      // Si fue a local (offline), encolar snapshot para el push posterior.
      if (!online && r.status >= 200 && r.status < 300) {
        const kind = snap.type === 'pedido' ? 'pedido offline' : 'venta offline';
        try {
          const id = snap.pickId(r.json);
          const snapshot = id ? await snap.fetch(Number(id)) : null;
          enqueueOp({
            type: snap.type,
            sync_uuid: req.body.sync_uuid,
            snapshot: serDates(snapshot
              ? { ...snapshot, sync_uuid: req.body.sync_uuid }
              : { ...req.body }),
          });
          log(`[proxy] ${kind} ${req.body.sync_uuid} guardado en local y encolado`);
        } catch (e) {
          enqueueOp({ type: snap.type, sync_uuid: req.body.sync_uuid, snapshot: { ...req.body } });
          log(`[proxy] ${kind} encolado por body (fallback): ${e.message}`);
        }
      }
      res.status(r.status);
      if (r.json) return res.json(r.json);
      return res.send(r.text);
    }

    // Otras escrituras: online -> web; offline -> local + replay (se reenvía tal cual al volver).
    const dest = online ? config.webBase : config.localBase;
    const key = online ? config.webSyncKey : config.localSyncKey;
    const r = await forward(dest, req, { 'x-sync-key': key });
    if (!online && r.status >= 200 && r.status < 300 && !req.originalUrl.startsWith('/api/sync/')) {
      if (replayMatch) {
        const uuid = await ensurePedidoUuid(replayMatch.m[1]);
        if (uuid) {
          enqueueOp({ type: 'replay_pedido', sync_uuid: uuid, action: replayMatch.action, payload: req.body || {} });
          log(`[proxy] ${replayMatch.action} pedido ${uuid.slice(0, 8)} offline -> local + replay encolado`);
        } else {
          enqueueOp({ type: 'replay', method: req.method, path: req.originalUrl, body: req.body || {} });
        }
      } else {
        enqueueOp({ type: 'replay', method: req.method, path: req.originalUrl, body: req.body || {} });
        log(`[proxy] ${req.method} ${req.originalUrl} offline -> local + replay encolado`);
      }
    }
    res.status(r.status);
    if (r.json) return res.json(r.json);
    res.set('content-type', r.contentType || 'text/plain');
    return res.send(r.text);
  } catch (e) {
    // Si la web falló a mitad de camino, degradar a local en lecturas y snapshot-writes.
    log(`[proxy] error contactando destino (${e.message}), degradando a local`);
    try {
      const r = await forward(config.localBase, req, { 'x-sync-key': config.localSyncKey });
      if (snap && r.status >= 200 && r.status < 300) {
        req.body = req.body || {};
        if (!req.body.sync_uuid) req.body.sync_uuid = randomUUID();
        enqueueOp({ type: snap.type, sync_uuid: req.body.sync_uuid, snapshot: { ...req.body } });
      }
      res.status(r.status);
      if (r.json) return res.json(r.json);
      return res.send(r.text);
    } catch (e2) {
      return res.status(502).json({ success: false, message: 'Ni web ni local responden', detail: String(e2.message || e2) });
    }
  }
});
