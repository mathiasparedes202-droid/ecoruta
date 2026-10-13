'use strict';
/* global ecoruta */
const $ = (id) => document.getElementById(id);
const FIELDS = ['webAppUrl', 'localAppUrl', 'webApiUrl', 'localApiUrl', 'agentUrl', 'syncKey', 'checkSeconds', 'repoPath'];

function say(msg) {
  const el = $('log');
  el.textContent = `[${new Date().toLocaleTimeString()}] ${msg}\n` + el.textContent;
}

async function refresh() {
  $('btnSave').disabled = true;
  const cfg = await ecoruta.getConfig();
  const brand = await ecoruta.getBrand();
  const lic = await ecoruta.getLicense();
  $('brandTitle').textContent = `${brand.company} · Conexiones`;
  $('brandCompany').value = brand.company || '';
  if (brand.color) $('brandColor').value = brand.color;
  $('licenseBox').innerHTML =
    `Estado: <b>${lic.state}</b> ${lic.company ? `· ${lic.company} (${lic.plan})` : ''}` +
    (lic.daysLeft > 0 ? ` · quedan ${lic.daysLeft} días` : '') +
    `<br><small>${lic.detail}</small>`;
  for (const f of FIELDS) {
    if (f === 'checkSeconds') { $('checkSeconds').value = cfg.checkSeconds; continue; }
    if (f === 'syncKey') continue; // no se muestra; placeholder indica estado
    if (f === 'repoPath') { $('repoPath').value = cfg.repoPath || ''; continue; }
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
  $('btnSave').disabled = false;
  say(`verificado: ${st.mode} -> ${st.target}`);
  try {
    const last = await ecoruta.lastAutostart();
    if (last && last.at) say(`stack local (${last.at}): ` + (last.results || []).map((r) => `${r.name}=${r.already ? 'ya' : r.started ? 'ok' : 'FALLO:' + r.error}`).join(' · '));
  } catch { /* agente viejo sin este canal */ }
}

$('btnCheck').onclick = refresh;
$('btnSync').onclick = async () => { await ecoruta.syncNow(); say('sincronización disparada');   await refresh();
};
$('btnReset').onclick = async () => {
  if (!confirm('¿Volver a los valores de fábrica? (la clave sync se conserva)')) return;
  await ecoruta.resetConfig();
  $('saveMsg').textContent = 'Valores restablecidos.';
  await refresh();
};
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
$('btnDetect').onclick = async () => {
  const r = await ecoruta.detectRepo();
  if (r.found) {
    $('repoPath').value = r.found;
    say('sistema encontrado en: ' + r.found + ' (pulsa Guardar)');
  } else {
    say('no se encontró el sistema en: ' + (r.checked || []).filter(Boolean).join(' · '));
  }
};

$('btnLicense').onclick = async () => {
  const r = await ecoruta.installLicense();
  say(r.installed ? 'licencia instalada.' : 'instalación cancelada' + (r.error ? ': ' + r.error : '.'));
  await refresh();
};
$('btnBrand').onclick = async () => {
  const b = await ecoruta.saveBrand({ company: $('brandCompany').value, color: $('brandColor').value });
  say('marca guardada: ' + b.company);
  await refresh();
};
refresh().catch((e) => say('error: ' + e.message));
