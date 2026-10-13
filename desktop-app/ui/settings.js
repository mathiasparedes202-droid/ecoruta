'use strict';
/* global ecoruta */
const $ = (id) => document.getElementById(id);
const FIELDS = ['webAppUrl', 'localAppUrl', 'webApiUrl', 'localApiUrl', 'agentUrl', 'syncKey', 'checkSeconds'];

function say(msg) {
  const el = $('log');
  el.textContent = `[${new Date().toLocaleTimeString()}] ${msg}\n` + el.textContent;
}

async function refresh() {
  const cfg = await ecoruta.getConfig();
  for (const f of FIELDS) {
    if (f === 'checkSeconds') { $('checkSeconds').value = cfg.checkSeconds; continue; }
    if (f === 'syncKey') continue; // no se muestra; placeholder indica estado
    $(f).value = cfg[f] || '';
  }
  $('syncKey').placeholder = cfg.syncKey ? '(guardada)' : '(vacía)';
  $('autostartLocal').checked = !!cfg.autostartLocal;
  const st = await ecoruta.statusNow();
  $('mode').textContent = st.mode === 'online_web' ? 'WEB EN LÍNEA' : 'MODO LOCAL';
  $('mode').className = 'pill ' + (st.mode === 'online_web' ? 'online' : 'offline');
  $('status').innerHTML =
    `Web: <b>${st.web.ok ? 'OK' : 'CAÍDA'}</b> (${st.web.status || st.web.error || '?'})<br>` +
    `Viendo: <code>${st.target || '…'}</code>`;
  say(`verificado: ${st.mode} -> ${st.target}`);
}

$('btnCheck').onclick = refresh;
$('btnSync').onclick = async () => { await ecoruta.syncNow(); say('sincronización disparada'); await refresh(); };
$('btnLocal').onclick = async () => {
  say('levantando stack local…');
  const res = await ecoruta.ensureLocal();
  say(JSON.stringify(res));
  await refresh();
};
$('btnSave').onclick = async () => {
  const next = {};
  for (const f of FIELDS) next[f] = $(f).value;
  next.checkSeconds = Math.max(5, Number(next.checkSeconds) || 15);
  next.autostartLocal = $('autostartLocal').checked;
  if (!next.syncKey) delete next.syncKey;
  await ecoruta.saveConfig(next);
  $('saveMsg').textContent = 'Guardado. La vigilancia usa los nuevos valores.';
  say('configuración guardada');
  await refresh();
};
$('btnTestWeb').onclick = async () => {
  const r = await ecoruta.testApi($('webApiUrl').value);
  say(`API web: ${r.ok ? 'OK' : 'FALLO'} ${r.status || ''} ${r.error || ''} (${r.ms} ms)`);
};
$('btnTestLocal').onclick = async () => {
  const r = await ecoruta.testApi($('localApiUrl').value);
  say(`API local: ${r.ok ? 'OK' : 'FALLO'} ${r.status || ''} ${r.error || ''} (${r.ms} ms)`);
};

refresh().catch((e) => say('error: ' + e.message));
