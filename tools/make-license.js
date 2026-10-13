'use strict';
// Licencias offline de la app comercial. La privada NUNCA sale de tools/.
// licensing.js (en la app) solo trae la pública y verifica.
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PRIV = path.join(__dirname, 'vendor-private.pem');

function args() {
  const o = {};
  for (let i = 2; i < process.argv.length; i += 2) o[process.argv[i].replace(/^--/, '')] = process.argv[i + 1];
  return o;
}

function canonical(obj) {
  const sorted = Object.fromEntries(Object.keys(obj).sort().map((k) => [k, obj[k]]));
  return JSON.stringify(sorted);
}

function main() {
  const a = args();
  if (!a.company || !a.out) {
    console.log('Uso: node tools/make-license.js --company "Nombre" --days 365 --plan pro --out licencia.json');
    process.exit(1);
  }
  const days = Math.max(1, Number(a.days) || 365);
  const payload = {
    company: String(a.company).slice(0, 80),
    plan: String(a.plan || 'pro').slice(0, 20),
    issued: new Date().toISOString().slice(0, 10),
    exp: new Date(Date.now() + days * 86400000).toISOString().slice(0, 10),
    seats: Number(a.seats) || 0, // 0 = ilimitados en esta versión
  };
  const key = crypto.createPrivateKey(fs.readFileSync(PRIV, 'utf8'));
  const sig = crypto.sign(null, Buffer.from(canonical(payload)), key).toString('base64');
  fs.writeFileSync(a.out, JSON.stringify({ ...payload, sig }, null, 2));
  console.log(`Licencia OK: ${a.company} plan=${payload.plan} exp=${payload.exp} -> ${a.out}`);
}

main();
