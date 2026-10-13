'use strict';
// Estado de licencia en la app (verificación offline con Ed25519).
// Sin licencia: 30 días de prueba desde el primer arranque.
// Vencida: la app queda en modo LOCAL + aviso (sin web ni sync disparado).
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

// MCowBQYDK2VwAyEAErho/v/OBIZjGWDuSCTNPlf1VnOuHgAJ1zwnCFp+yHE= se reemplaza al empaquetar con la pública real.
const VENDOR_PUBLIC_B64 = 'MCowBQYDK2VwAyEAErho/v/OBIZjGWDuSCTNPlf1VnOuHgAJ1zwnCFp+yHE=';
const TRIAL_DAYS = 30;

function publicKey() {
  // VENDOR_PUBLIC_B64 ya es el DER SPKI completo en base64 (con cabecera).
  const spki = Buffer.from(VENDOR_PUBLIC_B64, 'base64');
  return crypto.createPublicKey({ key: spki, format: 'der', type: 'spki' });
}

function canonical(obj) {
  const { sig, ...rest } = obj;
  const sorted = Object.fromEntries(Object.keys(rest).sort().map((k) => [k, rest[k]]));
  return JSON.stringify(sorted);
}

function verifyFile(file) {
  try {
    const lic = JSON.parse(fs.readFileSync(file, 'utf8'));
    if (!lic.sig || !lic.exp || !lic.company) return { ok: false, reason: 'formato' };
    const ok = crypto.verify(null, Buffer.from(canonical(lic)), publicKey(), Buffer.from(lic.sig, 'base64'));
    if (!ok) return { ok: false, reason: 'firma inválida' };
    const daysLeft = Math.floor((new Date(lic.exp + 'T23:59:59') - Date.now()) / 86400000);
    if (daysLeft < 0) return { ok: false, reason: 'vencida', lic };
    return { ok: true, lic, daysLeft };
  } catch {
    return { ok: false, reason: 'ilegible' };
  }
}

function trialStart(userData) {
  const f = path.join(userData, 'first_run');
  try {
    return fs.readFileSync(f, 'utf8').trim();
  } catch {
    const now = new Date().toISOString();
    try { fs.writeFileSync(f, now); } catch { /* efímero */ }
    return now;
  }
}

// Devuelve { state, company, plan, daysLeft, detail }.
// state: valid | trial | expired | none
function licenseStatus(userData, licenseFile) {
  if (VENDOR_PUBLIC_B64.length < 40) {
    return { state: 'none', company: '', plan: '', daysLeft: 0, detail: 'sin clave de vendor (build dev)' };
  }
  if (licenseFile && fs.existsSync(licenseFile)) {
    const v = verifyFile(licenseFile);
    if (v.ok) return { state: 'valid', company: v.lic.company, plan: v.lic.plan, daysLeft: v.daysLeft, detail: `licencia ${v.lic.plan}` };
    if (v.reason === 'vencida') {
      return { state: 'expired', company: v.lic?.company || '', plan: v.lic?.plan || '', daysLeft: 0, detail: 'licencia vencida' };
    }
    return { state: 'none', company: '', plan: '', daysLeft: 0, detail: 'licencia inválida: ' + v.reason };
  }
  const start = new Date(trialStart(userData)).getTime();
  const daysLeft = TRIAL_DAYS - Math.floor((Date.now() - start) / 86400000);
  if (daysLeft < 0) {
    return { state: 'expired', company: '', plan: '', daysLeft: 0, detail: 'prueba de 30 días terminada' };
  }
  return { state: 'trial', company: '', plan: '', daysLeft, detail: `prueba: quedan ${daysLeft} días` };
}

module.exports = { licenseStatus, TRIAL_DAYS };
