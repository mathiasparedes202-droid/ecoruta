import fs from 'node:fs';
import path from 'node:path';
import { config, log } from './config.js';
import { batchSettled } from './translate.js';

// Conector universal: une UN sistema con el Hub.
//  OUT: archivos {items:[...]} en DROP_DIR -> POST /hub/push -> se archivan en SENT_DIR.
//       Si algún item falla, el archivo queda y se reintenta (el hub deduplica).
//  IN:  GET /hub/pull con cursor persistido -> archivo en INBOX_DIR (+ POST a
//       CALLBACK_URL si está configurado). El cursor solo avanza si todo quedó.

function headers() {
  return { 'content-type': 'application/json', 'x-system-key': config.key };
}

export function readCursor() {
  try {
    const v = fs.readFileSync(config.cursorFile, 'utf8').trim();
    return v || '2000-01-01 00:00:00';
  } catch {
    return '2000-01-01 00:00:00';
  }
}

export function writeCursor(ts) {
  fs.mkdirSync(path.dirname(config.cursorFile), { recursive: true });
  fs.writeFileSync(config.cursorFile, String(ts));
}

export async function pushDropFiles() {
  const files = fs.readdirSync(config.dropDir).filter((f) => f.endsWith('.json')).sort();
  let pushed = 0;
  for (const f of files) {
    const full = path.join(config.dropDir, f);
    let data;
    try {
      data = JSON.parse(fs.readFileSync(full, 'utf8'));
    } catch (e) {
      log(`drop inválido ${f}: ${e.message} (se deja para revisión)`);
      continue;
    }
    const items = Array.isArray(data?.items) ? data.items : null;
    if (!items) {
      log(`drop sin items[] ${f} (se deja para revisión)`);
      continue;
    }
    if (!items.length) {
      fs.renameSync(full, path.join(config.sentDir, f));
      continue;
    }
    let resp;
    try {
      const r = await fetch(`${config.hub}/hub/push`, {
        method: 'POST', headers: headers(), body: JSON.stringify({ items: items.slice(0, 200) }),
      });
      resp = await r.json();
    } catch (e) {
      log(`hub inalcanzable, se reintenta luego (${e.message})`);
      return { pushed, stopped: 'offline' };
    }
    if (!resp?.success) {
      log(`hub rechazó ${f}: ${resp?.message || 'desconocido'} (se reintenta luego)`);
      return { pushed, stopped: 'rechazado' };
    }
    if (batchSettled(resp.results)) {
      fs.renameSync(full, path.join(config.sentDir, `${Date.now()}-${f}`));
      pushed += items.length;
      log(`drop ${f}: ${items.length} subidos`);
    } else {
      log(`drop ${f}: parcial, queda para reintento`);
      return { pushed, stopped: 'parcial' };
    }
  }
  return { pushed, stopped: null };
}

export async function pullToInbox() {
  const since = readCursor();
  let resp;
  try {
    const r = await fetch(
      `${config.hub}/hub/pull?since=${encodeURIComponent(since)}&limit=200`,
      { headers: headers() }
    );
    resp = await r.json();
  } catch (e) {
    log(`hub inalcanzable en pull (${e.message})`);
    return { pulled: 0, stopped: 'offline' };
  }
  if (!resp?.success) {
    log(`pull rechazado: ${resp?.message || 'desconocido'}`);
    return { pulled: 0, stopped: 'rechazado' };
  }
  const items = resp.items || [];
  if (!items.length) return { pulled: 0, stopped: null };
  const fname = `hub-${Date.now()}.json`;
  fs.writeFileSync(path.join(config.inboxDir, fname), JSON.stringify({ pulled_at: new Date().toISOString(), items }, null, 2));
  if (config.callback) {
    try {
      const r = await fetch(config.callback, {
        method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ items }),
      });
      if (!r.ok) {
        log(`callback ${r.status}, cursor NO avanza (se reintentará)`);
        return { pulled: items.length, stopped: 'callback' };
      }
    } catch (e) {
      log(`callback falló (${e.message}), cursor NO avanza`);
      return { pulled: items.length, stopped: 'callback' };
    }
  }
  writeCursor(resp.max_ts || since);
  log(`pull: ${items.length} nuevos (cursor ${resp.max_ts || since})`);
  return { pulled: items.length, stopped: null };
}

export async function runCycle() {
  if (!config.key) {
    log('sin SYSTEM_KEY: configura .env primero');
    return { push: { pushed: 0 }, pull: { pulled: 0 } };
  }
  const push = await pushDropFiles();
  const pull = await pullToInbox();
  return { push, pull };
}

const isMain = process.argv[1] && path.resolve(process.argv[1]) === path.join(import.meta.dirname, 'connector.js');
if (isMain) {
  log(`sistema=${config.system} hub=${config.hub} cada ${config.pollSeconds}s`);
  await runCycle();
  setInterval(runCycle, config.pollSeconds * 1000);
}
