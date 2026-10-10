import { Router, json } from 'express';
import { randomUUID } from 'node:crypto';
import { config } from './config.js';
import { loadState } from './store.js';
import { forward } from './webClient.js';
import { enqueueOp, log } from './store.js';
import { fetchLocalVenta } from './localDb.js';

export const proxy = Router();
proxy.use(json({ limit: '5mb' }));

// El frontend apunta aquí (http://localhost:18650/api/*).
// - GET: web si online, local si offline.
// - POST /api/ventas: web si online; si offline -> local + outbox (sync_uuid inyectado).
// - Resto de escrituras offline: se ejecutan en local y se encolan como replay genérico.
proxy.use('/api', async (req, res) => {
  const s = loadState();
  const online = s.mode === 'online_web' || s.webReachable !== false;
  const isRead = ['GET', 'HEAD'].includes(req.method);
  const isVentaWrite = req.method === 'POST' && /^\/api\/ventas\/?$/.test(req.originalUrl.split('?')[0]);

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

    if (isVentaWrite) {
      // Asegurar sync_uuid (idempotencia) antes de guardar en cualquier lado.
      req.body = req.body || {};
      if (!req.body.sync_uuid) req.body.sync_uuid = randomUUID();
      req.body.origen = online ? 'web' : 'local';

      const dest = online ? config.webBase : config.localBase;
      const key = online ? config.webSyncKey : config.localSyncKey;
      const r = await forward(dest, req, { 'x-sync-key': key });

      // Si fue a local (offline), encolar snapshot para el push posterior.
      if (!online && r.status >= 200 && r.status < 300) {
        try {
          const idVenta = r.json?.data?.venta?.id_venta || r.json?.data?.venta?.venta?.id_venta || r.json?.id_venta;
          const snapshot = idVenta ? await fetchLocalVenta(Number(idVenta)) : { ...req.body, detalles: req.body.detalles || [] };
          if (snapshot) {
            snapshot.sync_uuid = req.body.sync_uuid;
            enqueueOp({ type: 'venta', sync_uuid: req.body.sync_uuid, snapshot });
            log(`[proxy] venta offline ${req.body.sync_uuid} guardada en local y encolada`);
          }
        } catch (e) {
          enqueueOp({ type: 'venta', sync_uuid: req.body.sync_uuid, snapshot: { ...req.body, detalles: req.body.detalles || [] } });
          log(`[proxy] venta offline encolada por body (fallback): ${e.message}`);
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
      enqueueOp({ type: 'replay', method: req.method, path: req.originalUrl, body: req.body || {} });
      log(`[proxy] ${req.method} ${req.originalUrl} offline -> local + replay encolado`);
    }
    res.status(r.status);
    if (r.json) return res.json(r.json);
    res.set('content-type', r.contentType || 'text/plain');
    return res.send(r.text);
  } catch (e) {
    // Si la web falló a mitad de camino, degradar a local solo en lecturas/ventas.
    log(`[proxy] error contactando destino (${e.message}), degradando a local`);
    try {
      const r = await forward(config.localBase, req, { 'x-sync-key': config.localSyncKey });
      if (isVentaWrite && r.status >= 200 && r.status < 300) {
        req.body = req.body || {};
        if (!req.body.sync_uuid) req.body.sync_uuid = randomUUID();
        enqueueOp({ type: 'venta', sync_uuid: req.body.sync_uuid, snapshot: { ...req.body, detalles: req.body.detalles || [] } });
      }
      res.status(r.status);
      if (r.json) return res.json(r.json);
      return res.send(r.text);
    } catch (e2) {
      return res.status(502).json({ success: false, message: 'Ni web ni local responden', detail: String(e2.message || e2) });
    }
  }
});
