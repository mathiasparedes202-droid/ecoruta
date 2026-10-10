import { config } from './config.js';

async function fetchTimeout(url, opts = {}, ms = config.healthTimeoutMs) {
  const ctl = new AbortController();
  const t = setTimeout(() => ctl.abort(), ms);
  try {
    return await fetch(url, { ...opts, signal: ctl.signal });
  } finally {
    clearTimeout(t);
  }
}

export async function checkHealth(base) {
  try {
    const r = await fetchTimeout(`${base}/api/sync/health`, {}, config.healthTimeoutMs);
    if (!r.ok) return false;
    const j = await r.json();
    return j?.success === true;
  } catch {
    return false;
  }
}

// Proxy genérico: reenvía al backend destino conservando método, query y body.
export async function forward(base, req, extraHeaders = {}) {
  const url = base + req.originalUrl.replace(/^\/api/, '/api');
  const headers = { ...extraHeaders };
  const ct = req.get('content-type');
  if (ct) headers['content-type'] = ct;
  const auth = req.get('authorization');
  if (auth) headers['authorization'] = auth;

  const hasBody = !['GET', 'HEAD'].includes(req.method);
  const r = await fetchTimeout(
    url,
    {
      method: req.method,
      headers,
      body: hasBody ? JSON.stringify(req.body ?? {}) : undefined,
    },
    30000
  );
  const text = await r.text();
  let json = null;
  try {
    json = JSON.parse(text);
  } catch {
    /* respuesta no-JSON (html/pdf): se devuelve cruda */
  }
  return { status: r.status, json, text, contentType: r.headers.get('content-type') || '' };
}

export async function webPush({ pedidos = [], ventas = [], replays = [] }) {
  const r = await fetchTimeout(
    `${config.webBase}/api/sync/push`,
    {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'x-sync-key': config.webSyncKey },
      body: JSON.stringify({ pedidos, ventas, replays }),
    },
    60000
  );
  const body = await r.json().catch(() => ({}));
  return { ...body, _http: r.status };
}

export async function webPull(since, limit = 200) {
  const u = new URL(`${config.webBase}/api/sync/pull`);
  u.searchParams.set('since', since);
  u.searchParams.set('limit', String(limit));
  const r = await fetchTimeout(u, { headers: { 'x-sync-key': config.webSyncKey } }, 60000);
  return r.json();
}

// Aplica en LOCAL lo que vino de la web usando el MISMO endpoint idempotente.
// Así la lógica anti-duplicado vive en un solo lugar (PHP).
export async function localPush({ pedidos = [], ventas = [], replays = [] }) {
  const r = await fetchTimeout(
    `${config.localBase}/api/sync/push`,
    {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'x-sync-key': config.localSyncKey },
      body: JSON.stringify({ pedidos, ventas, replays }),
    },
    60000
  );
  return r.json();
}
