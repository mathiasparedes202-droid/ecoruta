import fs from 'node:fs';
import path from 'node:path';

const __dirname = import.meta.dirname;

function env(name, def = '') {
  const v = process.env[name];
  return v === undefined || v === '' ? def : v;
}

export const config = {
  hub: env('HUB_URL', 'http://127.0.0.1:4000').replace(/\/$/, ''),
  system: env('SYSTEM_NAME', 'mi-sistema'),
  key: env('SYSTEM_KEY', ''),
  pollSeconds: Math.max(5, Number(env('POLL_SECONDS', 15)) || 15),
  dropDir: path.resolve(__dirname, '..', env('DROP_DIR', './data/drop')),
  inboxDir: path.resolve(__dirname, '..', env('INBOX_DIR', './data/inbox')),
  sentDir: path.resolve(__dirname, '..', env('SENT_DIR', './data/sent')),
  cursorFile: path.resolve(__dirname, '..', env('CURSOR_FILE', './data/cursor.txt')),
  callback: env('CALLBACK_URL', ''),
};

for (const d of [config.dropDir, config.inboxDir, config.sentDir]) {
  fs.mkdirSync(d, { recursive: true });
}

export function log(...a) {
  console.log('[conector]', ...a);
}
