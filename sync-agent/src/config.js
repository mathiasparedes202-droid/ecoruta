import 'dotenv/config';

function num(name, def) {
  const v = Number(process.env[name]);
  return Number.isFinite(v) && v > 0 ? v : def;
}

export const config = {
  port: num('PORT', 18650),
  localBase: (process.env.LOCAL_BASE_URL || 'http://localhost:8000').replace(/\/$/, ''),
  localSyncKey: process.env.LOCAL_SYNC_KEY || '',
  webBase: (process.env.WEB_BASE_URL || '').replace(/\/$/, ''),
  webSyncKey: process.env.WEB_SYNC_KEY || '',
  pollSeconds: num('POLL_SECONDS', 15),
  healthTimeoutMs: num('HEALTH_TIMEOUT_MS', 8000),
  db: {
    host: process.env.LOCAL_DB_HOST || '127.0.0.1',
    port: num('LOCAL_DB_PORT', 3306),
    database: process.env.LOCAL_DB_NAME || 'ecoruta_db',
    user: process.env.LOCAL_DB_USER || 'root',
    password: process.env.LOCAL_DB_PASS ?? '',
  },
};

if (!config.webBase) console.warn('[sync-agent] WEB_BASE_URL vacío: trabajará solo contra local hasta configurarlo.');
if (!config.webSyncKey) console.warn('[sync-agent] WEB_SYNC_KEY vacío: el push/pull a la web será rechazado (401).');
