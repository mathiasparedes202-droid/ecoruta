'use strict';
// EcoRuta Desktop — proceso principal.
// - Ventana única que muestra la app WEB o la LOCAL según salud (auto-failover).
// - Bandeja: estado, modo (auto/forzar web/forzar local), sincronizar, ajustes, salir.
// - Stack local integrado: levanta PHP + Sync Agent + web local si no responden.
// - Ajustes por sistema: URLs + clave sync se guardan en userData (fuera del repo).
// - Smoke test: `electron . --smoke` verifica sin abrir ventanas.

const { app, BrowserWindow, Tray, Menu, Notification, ipcMain, nativeImage, shell } = require('electron');
const path = require('path');
const fs = require('fs');
const { spawn, execFileSync } = require('child_process');

const REPO_ROOT = path.join(__dirname, '..');
const SMOKE = process.argv.includes('--smoke');

const DEFAULTS = {
  webAppUrl: 'https://ecoruta-six.vercel.app',
  localAppUrl: 'http://localhost:5173',
  webApiUrl: 'https://ecoruta-jaxf.onrender.com',
  localApiUrl: 'http://127.0.0.1:8000',
  agentUrl: 'http://localhost:18650',
  syncKey: '',
  checkSeconds: 15,
  mode: 'auto', // auto | web | local
  autostartLocal: true,
};

let cfg = { ...DEFAULTS };
let cfgPath = '';
let mainWin = null;
let settingsWin = null;
let tray = null;
let currentTarget = '';
let lastMode = 'unknown';
let children = [];
let timer = null;

function log(...a) { console.log('[desktop]', ...a); }

// ---------- Config ----------
const URL_FIELDS = ['webAppUrl', 'localAppUrl', 'webApiUrl', 'localApiUrl', 'agentUrl'];
function withDefaults(c) {
  // Un campo vacío nunca puede romper el failover: vuelve al valor de fábrica.
  const out = { ...DEFAULTS, ...c };
  for (const f of URL_FIELDS) {
    if (!String(out[f] || '').trim()) out[f] = DEFAULTS[f];
  }
  if (!Number(out.checkSeconds) || out.checkSeconds < 5) out.checkSeconds = DEFAULTS.checkSeconds;
  return out;
}
function loadConfig() {
  cfgPath = path.join(app.getPath('userData'), 'ecoruta-desktop-config.json');
  try {
    const raw = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));
    cfg = withDefaults(raw);
  } catch { cfg = { ...DEFAULTS }; }
  return cfg;
}
function saveConfig(next) {
  cfg = withDefaults({ ...cfg, ...next });
  fs.writeFileSync(cfgPath, JSON.stringify(cfg, null, 2));
  return cfg;
}

// ---------- HTTP ----------
async function checkUrl(url, ms = 8000) {
  const ctl = new AbortController();
  const t = setTimeout(() => ctl.abort(), ms);
  const start = Date.now();
  try {
    const r = await fetch(url, { signal: ctl.signal });
    const text = await r.text().catch(() => '');
    let json = null;
    try { json = JSON.parse(text); } catch { /* no-json */ }
    return { ok: r.ok, status: r.status, ms: Date.now() - start, json, text: text.slice(0, 200) };
  } catch (e) {
    return { ok: false, status: 0, ms: Date.now() - start, error: String(e.message || e) };
  } finally {
    clearTimeout(t);
  }
}

async function apiHealth(base) {
  // Prefiere /api/sync/health (nuevo), cae a /api/health (viejo).
  const r1 = await checkUrl(base.replace(/\/$/, '') + '/api/sync/health');
  if (r1.ok && r1.json && r1.json.success !== false) return { ...r1, ok: true };
  const r2 = await checkUrl(base.replace(/\/$/, '') + '/api/health');
  return r2;
}

// ---------- Stack local (como iniciar-ecoruta) ----------
function binExists(bin) {
  try {
    execFileSync(process.platform === 'win32' ? 'where' : 'which', [bin], { stdio: 'ignore' });
    return true;
  } catch { return false; }
}

async function ensureService({ name, url, cmd, args, cwd }) {
  const h = await checkUrl(url, 4000);
  if (h.ok) return { name, already: true };
  if (!fs.existsSync(cwd)) return { name, error: 'carpeta no encontrada: ' + cwd };
  let child;
  try {
    child = spawn(cmd, args, { cwd, detached: true, stdio: 'ignore', windowsHide: true });
    await new Promise((resolve, reject) => {
      child.once('error', reject);
      child.once('spawn', resolve);
    });
    child.unref();
  } catch (e) {
    return { name, error: 'no se pudo lanzar: ' + String(e.message || e) };
  }
  children.push(child);
  // Espera a que responda (hasta ~25 s)
  for (let i = 0; i < 25; i++) {
    await new Promise((r) => setTimeout(r, 1000));
    const h2 = await checkUrl(url, 3000);
    if (h2.ok) return { name, started: true, pid: child.pid };
  }
  return { name, error: 'no respondió en 25 s' };
}

async function ensureLocalStack(onEvent) {
  const emit = (m) => { log(m); if (onEvent) onEvent(m); };
  const out = [];
  if (!binExists('php') || !binExists('node')) {
    return [{ name: 'requisitos', error: 'falta php o node en el PATH' }];
  }
  const agentEnvOk = fs.existsSync(path.join(REPO_ROOT, 'sync-agent', '.env'))
    && fs.existsSync(path.join(REPO_ROOT, 'sync-agent', 'node_modules'));
  const webDistOk = fs.existsSync(path.join(REPO_ROOT, 'frontend', 'dist', 'index.html'))
    || fs.existsSync(path.join(REPO_ROOT, 'EcoRuta-app', 'dist', 'index.html'));
  emit(`requisitos php/node ok · agent listo=${agentEnvOk} · dist=${webDistOk}`);

  const apiPort = new URL(cfg.localApiUrl).port || '8000';
  out.push(await ensureService({
    name: 'api-local', url: `${cfg.localApiUrl}/api/health`,
    cmd: 'php', args: ['-S', `127.0.0.1:${apiPort}`, 'public/router.php'],
    cwd: path.join(REPO_ROOT, 'backend'),
  }));
  const agentPort = new URL(cfg.agentUrl).port || '18650';
  if (agentEnvOk) {
    out.push(await ensureService({
      name: 'sync-agent', url: `${cfg.agentUrl}/agent/status`,
      cmd: 'node', args: ['src/server.js'],
      cwd: path.join(REPO_ROOT, 'sync-agent'),
    }));
  } else {
    out.push({ name: 'sync-agent', error: 'falta sync-agent/.env o npm install en sync-agent' });
  }
  try {
    const webPort = new URL(cfg.localAppUrl).port || '5173';
    // Sin shell: .cmd no arranca con spawn directo; se corre el JS con node.
    const viteJs = path.join(REPO_ROOT, 'frontend', 'node_modules', 'vite', 'bin', 'vite.js');
    if (webDistOk && fs.existsSync(viteJs)) {
      out.push(await ensureService({
        name: 'web-local', url: cfg.localAppUrl,
        cmd: 'node', args: [viteJs, 'preview', '--port', String(webPort), '--strictPort', '--host', '127.0.0.1'],
        cwd: path.join(REPO_ROOT, 'frontend'),
      }));
    } else {
      out.push({ name: 'web-local', error: 'falta frontend/dist o node_modules/vite' });
    }
  } catch (e) {
    out.push({ name: 'web-local', error: String(e.message || e) });
  }
  return out;
}

let lastAutostart = { at: null, results: [] };

// ---------- Failover ----------
function targetFor(webHealthy) {
  if (cfg.mode === 'web') return cfg.webAppUrl;
  if (cfg.mode === 'local') return cfg.localAppUrl;
  return webHealthy ? cfg.webAppUrl : cfg.localAppUrl;
}

function setWindowTarget(url) {
  if (!mainWin || currentTarget === url) return;
  currentTarget = url;
  mainWin.loadURL(url).catch((e) => log('loadURL:', e.message));
  mainWin.setTitle(`EcoRuta — ${url.includes('localhost') || url.includes('127.0.0.1') ? 'LOCAL' : 'WEB'}`);
}

function notify(title, body) {
  try { new Notification({ title, body }).show(); } catch { /* sin notificaciones */ }
}

async function triggerAgentSync() {
  try {
    const r = await checkUrl(cfg.agentUrl.replace(/\/$/, '') + '/agent/sync-now', 30000);
    log('sync-now:', r.status);
  } catch (e) { log('sync-now falló:', e.message); }
}

async function tick(fromUser = false) {
  const web = await apiHealth(cfg.webApiUrl);
  const healthy = !!web.ok;
  const mode = healthy ? 'online_web' : 'offline_web';
  const prevTarget = currentTarget;
  setWindowTarget(targetFor(healthy));
  if (mode !== lastMode) {
    log(`modo: ${lastMode} -> ${mode}`);
    if (lastMode === 'offline_web' && mode === 'online_web') {
      notify('EcoRuta: web en línea', 'Sincronizando pendientes…');
      await triggerAgentSync();
    }
    if (mode === 'offline_web' && lastMode !== 'unknown') {
      notify('EcoRuta: web caída', 'Trabajando con el sistema local.');
    }
    lastMode = mode;
    buildTray();
  } else if (fromUser) {
    setWindowTarget(targetFor(healthy));
  }
  if (prevTarget && prevTarget !== currentTarget) buildTray();
  return { mode, web, target: currentTarget };
}

// ---------- Ventanas ----------
function createMain() {
  mainWin = new BrowserWindow({
    width: 1366, height: 860,
    autoHideMenuBar: false,
    webPreferences: { contextIsolation: true },
  });
  mainWin.on('closed', () => { mainWin = null; });
  // Enlaces externos (docs, etc.) en el navegador del sistema
  mainWin.webContents.setWindowOpenHandler(({ url }) => {
    if (!url.startsWith(cfg.webAppUrl) && !url.startsWith(cfg.localAppUrl)) {
      shell.openExternal(url).catch(() => {});
      return { action: 'deny' };
    }
    return { action: 'allow' };
  });
}

function openSettings() {
  if (settingsWin) { settingsWin.focus(); return; }
  settingsWin = new BrowserWindow({
    width: 640, height: 720,
    title: 'EcoRuta — Conexiones',
    webPreferences: { preload: path.join(__dirname, 'preload.js'), contextIsolation: true },
  });
  settingsWin.loadFile(path.join(__dirname, 'ui', 'settings.html')).catch((e) => log(e.message));
  settingsWin.on('closed', () => { settingsWin = null; });
}

// ---------- Bandeja ----------
function trayIcon() {
  const p = path.join(REPO_ROOT, 'EcoRuta Logo.png');
  try {
    if (fs.existsSync(p)) return nativeImage.createFromPath(p).resize({ width: 16, height: 16 });
  } catch { /* icono por defecto */ }
  return undefined;
}

function buildTray() {
  const label = lastMode === 'online_web' ? 'EcoRuta: WEB' : lastMode === 'offline_web' ? 'EcoRuta: LOCAL' : 'EcoRuta';
  if (!tray) {
    tray = new Tray(trayIcon() || nativeImage.createEmpty());
    tray.setToolTip(label);
  }
  tray.setTitle('');
  tray.setToolTip(`${label} → ${currentTarget || ''}`);
  const modeMenu = [
    { label: 'Automático', type: 'radio', checked: cfg.mode === 'auto', click: () => { saveConfig({ mode: 'auto' }); void tick(true); buildTray(); } },
    { label: 'Forzar web', type: 'radio', checked: cfg.mode === 'web', click: () => { saveConfig({ mode: 'web' }); void tick(true); buildTray(); } },
    { label: 'Forzar local', type: 'radio', checked: cfg.mode === 'local', click: () => { saveConfig({ mode: 'local' }); void tick(true); buildTray(); } },
  ];
  tray.setContextMenu(Menu.buildFromTemplate([
    { label: label, enabled: false },
    { type: 'separator' },
    ...modeMenu,
    { type: 'separator' },
    { label: 'Sincronizar ahora', click: () => { void triggerAgentSync(); } },
    { label: 'Verificar conexiones', click: () => { void tick(true); } },
    { label: 'Conexiones…', click: openSettings },
    { type: 'separator' },
    { label: 'Salir', click: () => app.quit() },
  ]));
}

// ---------- IPC (ventana de ajustes; evita CORS: todo sale del main) ----------
ipcMain.handle('cfg-get', () => ({ ...cfg, syncKey: cfg.syncKey ? '••••••••' : '' }));
ipcMain.handle('cfg-save', (_ev, next) => {
  if (!next.syncKey || next.syncKey === '••••••••') delete next.syncKey;
  const saved = saveConfig(next);
  void tick(true);
  buildTray();
  return { ...saved, syncKey: saved.syncKey ? '••••••••' : '' };
});
ipcMain.handle('cfg-reset', () => {
  const saved = saveConfig({ ...DEFAULTS, syncKey: cfg.syncKey });
  void tick(true);
  buildTray();
  return { ...saved, syncKey: saved.syncKey ? '••••••••' : '' };
});
ipcMain.handle('net-test', async (_ev, url) => checkUrl(url, 10000));
ipcMain.handle('api-test', async (_ev, base) => apiHealth(base));
ipcMain.handle('status-now', async () => tick(true));
ipcMain.handle('sync-now', async () => { await triggerAgentSync(); return { ok: true }; });
ipcMain.handle('local-ensure', async () => {
  const res = await ensureLocalStack();
  lastAutostart = { at: new Date().toISOString(), results: res };
  return res;
});
ipcMain.handle('local-last', () => lastAutostart);

// ---------- Arranque ----------
function appendBootLog(text) {
  try {
    const dir = path.join(app.getPath('userData'), 'logs');
    fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(path.join(dir, 'autostart.log'), `[${new Date().toISOString()}] ${text}\n`);
  } catch { /* diagnóstico best-effort */ }
}

async function boot() {
  loadConfig();
  if (SMOKE) return smoke();
  await app.whenReady();
  createMain();
  buildTray();
  if (cfg.autostartLocal) {
    log('levantando stack local…');
    try {
      const res = await ensureLocalStack(log);
      lastAutostart = { at: new Date().toISOString(), results: res };
      for (const r of res) appendBootLog(`${r.name}: ${r.already ? 'ya corría' : r.started ? 'levantado' : 'FALLO: ' + r.error}`);
      const failed = res.filter((r) => r.error);
      if (failed.length) {
        notify('EcoRuta: stack local incompleto', failed.map((r) => `${r.name}: ${r.error}`).join(' · ').slice(0, 220));
      }
    } catch (e) {
      appendBootLog('FALLO general autostart: ' + String(e.message || e));
      notify('EcoRuta: no se pudo levantar lo local', String(e.message || e).slice(0, 220));
    }
  }
  await tick();
  timer = setInterval(() => { void tick(); }, Math.max(5, cfg.checkSeconds) * 1000);
}

async function smoke() {
  await app.whenReady();
  const results = [];
  const t = async (name, fn) => {
    try { const v = await fn(); results.push([name, 'OK', v || '']); }
    catch (e) { results.push([name, 'FAIL', String(e.message || e)]); }
  };
  await t('config-load-save', async () => {
    loadConfig(); saveConfig({ checkSeconds: cfg.checkSeconds });
    if (!fs.existsSync(cfgPath)) throw new Error('sin archivo');
    return cfgPath;
  });
  await t('health-dead-port-offline', async () => {
    const r = await checkUrl('http://127.0.0.1:9/xyz', 2000);
    if (r.ok) throw new Error('debió fallar');
    return 'offline detectado';
  });
  await t('binarios-php-node', async () => {
    if (!binExists('node')) throw new Error('sin node');
    return 'node OK' + (binExists('php') ? ' php OK' : ' (php ausente)');
  });
  await t('apiHealth-caida', async () => {
    const r = await apiHealth('http://127.0.0.1:9');
    if (r.ok) throw new Error('debió fallar');
    return 'fallback OK';
  });
  console.log('SMOKE:', JSON.stringify(results));
  const failed = results.filter((r) => r[1] !== 'OK');
  await new Promise((r) => setTimeout(r, 300));
  app.exit(failed.length ? 1 : 0);
}

app.on('window-all-closed', () => { if (process.platform !== 'darwin') app.quit(); });
app.on('before-quit', () => {
  if (timer) clearInterval(timer);
  for (const c of children) { try { c.kill(); } catch { /* ya murió */ } }
});

void boot();
