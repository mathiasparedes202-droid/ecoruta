// Codec de paquetes SMS para sync offline a escala nacional (500 nodos).
// Cero dependencias. Alfabeto GSM-7 seguro, 1 SMS por evento crítico.
//
// Formato: ECO <OP> <campos...> <CRC>
//  OP: P1 pedido creado | P2 pago | P3 estado | P4 cancelación
//  CRC: 2 chars base36 (FNV-1a mod 1296) sobre todo lo anterior.
// Números: enteros; timestamps en base36 (segundos epoch).
// Limitación honesta: el SMS lleva el mínimo para no perder la operación
// (uuid + totales + refs cortas). El detalle completo viaja cuando hay IP.

const ALPHA = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const MAGIC = 'ECO';

export const SMS_OPS = { P1: 'pedido', P2: 'pago', P3: 'estado', P4: 'cancel' };

function crc2(body) {
  let h = 0x811c9dc5;
  for (let i = 0; i < body.length; i++) {
    h ^= body.charCodeAt(i);
    h = Math.imul(h, 0x01000193) >>> 0;
  }
  const v = h % 1296;
  return ALPHA[Math.floor(v / 36)] + ALPHA[v % 36];
}

// Mayúsculas, sin tildes/Ñ, solo GSM-7 seguro.
export function gsmSafe(s, max = 0) {
  let t = String(s ?? '')
    .toUpperCase()
    .replace(/[ÁÀÄÂ]/g, 'A').replace(/[ÉÈËÊ]/g, 'E').replace(/[ÍÌÏÎ]/g, 'I')
    .replace(/[ÓÒÖÔ]/g, 'O').replace(/[ÚÙÜÛ]/g, 'U').replace(/Ñ/g, 'N')
    .replace(/[^A-Z0-9 .,#*+\-/=]/g, '');
  if (max > 0) t = t.slice(0, max);
  return t.trim();
}

export function b36(n) {
  return Math.round(Number(n)).toString(36).toUpperCase();
}
export function unb36(s) {
  const v = parseInt(String(s), 36);
  if (!Number.isFinite(v)) throw new Error('base36 inválido: ' + s);
  return v;
}

function pack(op, fields) {
  const body = [MAGIC, op, ...fields].join(' ');
  return `${body} ${crc2(body)}`;
}

// ---- Encoders (1 SMS por evento) ----

export function encodePedidoCreado({ sync_uuid, id_comercio, tarifa_ecologica, metodo_pago = 'efectivo', pagado = 0, ts = Date.now() }) {
  if (!sync_uuid || sync_uuid.length < 8) throw new Error('sync_uuid requerido');
  const m = { efectivo: 'E', transferencia: 'T', mixto: 'M' }[metodo_pago] || 'E';
  const total = Math.round(Number(tarifa_ecologica));
  if (!(total > 0)) throw new Error('tarifa_ecologica inválida');
  return pack('P1', [sync_uuid, b36(id_comercio || 0), String(total), m, pagado ? '1' : '0', b36(Math.floor(ts / 1000))]);
}

export function encodePago({ sync_uuid, monto_recibido, comprobante = '', ts = Date.now() }) {
  if (!sync_uuid || sync_uuid.length < 8) throw new Error('sync_uuid requerido');
  const monto = Math.round(Number(monto_recibido));
  if (!(monto > 0)) throw new Error('monto_recibido inválido');
  // El comprobante completo no entra: viaja ref corta, el resto con IP.
  const ref = gsmSafe(comprobante).replace(/[^A-Z0-9]/g, '').slice(-8) || '-';
  return pack('P2', [sync_uuid, String(monto), ref, b36(Math.floor(ts / 1000))]);
}

export function encodeEstado({ sync_uuid, id_estado, ts = Date.now() }) {
  if (!sync_uuid || sync_uuid.length < 8) throw new Error('sync_uuid requerido');
  const e = Number(id_estado);
  if (![1, 2, 3, 4, 5].includes(e)) throw new Error('id_estado inválido');
  return pack('P3', [sync_uuid, String(e), b36(Math.floor(ts / 1000))]);
}

export function encodeCancel({ sync_uuid, motivo, ts = Date.now() }) {
  if (!sync_uuid || sync_uuid.length < 8) throw new Error('sync_uuid requerido');
  const m = gsmSafe(motivo, 60).replace(/ /g, '_');
  if (m.length < 5) throw new Error('motivo mínimo 5 caracteres');
  return pack('P4', [sync_uuid, m, b36(Math.floor(ts / 1000))]);
}

// ---- Decoder ----

export function decodeSms(text) {
  const parts = String(text || '').trim().split(/\s+/);
  if (parts.length < 4) throw new Error('paquete incompleto');
  const crc = parts.pop();
  const body = parts.join(' ');
  if (crc !== crc2(body)) throw new Error('CRC inválido (SMS corrupto o ajeno)');
  const [magic, op, ...f] = parts;
  if (magic !== MAGIC) throw new Error('no es paquete EcoRuta');
  if (!SMS_OPS[op]) throw new Error('operación desconocida: ' + op);

  if (op === 'P1') {
    if (f.length !== 6) throw new Error('P1 malformado');
    const metodo = { E: 'efectivo', T: 'transferencia', M: 'mixto' }[f[3]];
    if (!metodo) throw new Error('método inválido');
    return { op: 'pedido', sync_uuid: f[0], id_comercio: unb36(f[1]), tarifa_ecologica: Number(f[2]), metodo_pago: metodo, pagado: f[4] === '1', ts: unb36(f[5]) * 1000 };
  }
  if (op === 'P2') {
    if (f.length !== 4) throw new Error('P2 malformado');
    return { op: 'pago', sync_uuid: f[0], monto_recibido: Number(f[1]), comprobante_ref: f[2] === '-' ? null : f[2], ts: unb36(f[3]) * 1000 };
  }
  if (op === 'P3') {
    if (f.length !== 3) throw new Error('P3 malformado');
    return { op: 'estado', sync_uuid: f[0], id_estado: Number(f[1]), ts: unb36(f[2]) * 1000 };
  }
  // P4
  if (f.length !== 3) throw new Error('P4 malformado');
  return { op: 'cancel', sync_uuid: f[0], motivo: f[1].replace(/_/g, ' '), ts: unb36(f[2]) * 1000 };
}

// ---- Chunking (mensajes largos en N SMS) ----
// ECO CN <msgid4> <seq>/<total> <chunk...> <CRC>  (chunk ≤ 100 chars)

export function splitSms(text, chunkLen = 100) {
  const words = String(text).split(' ');
  const chunks = [];
  let cur = '';
  for (const w of words) {
    const next = cur ? cur + ' ' + w : w;
    if (next.length > chunkLen && cur) {
      chunks.push(cur);
      cur = w;
    } else {
      cur = next;
    }
  }
  if (cur) chunks.push(cur);
  // Sin espacios en bordes: split(/\s+/) los colapsaría y rompería el CRC.
  const clean = chunks.map((c) => c.trim()).filter(Boolean);
  const msgId = ALPHA[Math.floor(Math.random() * 36)] + ALPHA[Math.floor(Math.random() * 36)] + ALPHA[Math.floor(Math.random() * 36)] + ALPHA[Math.floor(Math.random() * 36)];
  return clean.map((c, i) => {
    const body = `${MAGIC} CN ${msgId} ${i + 1}/${clean.length} ${c}`;
    return `${body} ${crc2(body)}`;
  });
}

export function joinSms(parts) {
  // parts: array de textos ya decodificados por CRC; ordena y une por msgId.
  const byId = new Map();
  for (const text of parts) {
    const p = String(text).trim().split(/\s+/);
    const crc = p.pop();
    const body = p.join(' ');
    if (crc !== crc2(body)) throw new Error('CRC inválido en fragmento');
    const [, , msgId, seqTot, ...rest] = p;
    if (!byId.has(msgId)) byId.set(msgId, { total: 0, got: new Map() });
    const g = byId.get(msgId);
    const [seq, total] = seqTot.split('/').map(Number);
    g.total = total;
    g.got.set(seq, rest.join(' '));
  }
  const out = [];
  for (const [id, g] of byId) {
    if (g.got.size !== g.total) throw new Error(`mensaje ${id} incompleto (${g.got.size}/${g.total})`);
    out.push({ msgId: id, text: [...g.got.entries()].sort((a, b) => a[0] - b[0]).map(([, v]) => v).join(' ') });
  }
  return out;
}
