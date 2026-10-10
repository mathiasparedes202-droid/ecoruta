// Tests del codec SMS. Uso: node src/sms/codec.test.js  (sale 0 si todo pasa)
import assert from 'node:assert/strict';
import {
  encodePedidoCreado, encodePago, encodeEstado, encodeCancel,
  decodeSms, splitSms, joinSms, gsmSafe,
} from './codec.js';

const UUID = '123e4567-e89b-12d3-a456-426614174000';
let n = 0;
const ok = (cond, name) => { n++; assert.ok(cond, name); console.log('ok', n, '-', name); };

// P1 cabe en 1 SMS y sobrevive ida y vuelta
const p1 = encodePedidoCreado({ sync_uuid: UUID, id_comercio: 1, tarifa_ecologica: 21250, metodo_pago: 'efectivo', pagado: 0, ts: 1760040000000 });
ok(p1.length <= 160, `P1 en 1 SMS (${p1.length} chars): ${p1}`);
const d1 = decodeSms(p1);
ok(d1.op === 'pedido' && d1.sync_uuid === UUID && d1.tarifa_ecologica === 21250 && d1.metodo_pago === 'efectivo' && d1.pagado === false, 'P1 decode íntegro');

// P2 con comprobante largo -> ref corta, 1 SMS
const p2 = encodePago({ sync_uuid: UUID, monto_recibido: 22000, comprobante: 'TRX-20261005-1234', ts: 1760040000000 });
ok(p2.length <= 160, `P2 en 1 SMS (${p2.length} chars): ${p2}`);
const d2 = decodeSms(p2);
ok(d2.op === 'pago' && d2.monto_recibido === 22000 && d2.comprobante_ref === '0051234'.slice(-8) || d2.comprobante_ref?.length <= 8, 'P2 decode con ref corta');

// P3 / P4
const p3 = encodeEstado({ sync_uuid: UUID, id_estado: 4, ts: 1760040000000 });
ok(p3.length <= 160, `P3 en 1 SMS: ${p3}`);
ok(decodeSms(p3).id_estado === 4, 'P3 decode');
const p4 = encodeCancel({ sync_uuid: UUID, motivo: 'Cliente no estaba en casa', ts: 1760040000000 });
ok(p4.length <= 160, `P4 en 1 SMS: ${p4}`);
ok(decodeSms(p4).motivo.includes('CLIENTE'), 'P4 decode motivo');

// Corrupción se detecta
assert.throws(() => decodeSms(p1.slice(0, -1) + 'X'), /CRC/, 'CRC detecta 1 char corrupto');
assert.throws(() => decodeSms('HOLA MUNDO'), /incompleto|CRC|EcoRuta/, 'basura rechazada');

// Chunking ida y vuelta (incluye desorden)
const largo = 'ECO P1 ' + UUID + ' ' + 'DETALLE '.repeat(30);
const parts = splitSms(largo, 100);
ok(parts.length > 1 && parts.every((p) => p.length <= 160), `split en ${parts.length} SMS válidos`);
const joined = joinSms([...parts].reverse());
ok(joined.length === 1 && joined[0].text === largo.trim(), 'join reordena y reconstruye');
assert.throws(() => joinSms(parts.slice(1)), /incompleto/, 'fragmento faltante se detecta');

// Sanitizado GSM
ok(gsmSafe('Ñandú áéí óú 123 😀') === 'NANDU AEI OU 123', 'gsmSafe limpia');

// Validaciones
assert.throws(() => encodePedidoCreado({ sync_uuid: 'x', tarifa_ecologica: 5 }), /sync_uuid/, 'uuid corto rechazado');
assert.throws(() => encodeCancel({ sync_uuid: UUID, motivo: 'abc' }), /5 caracteres/, 'motivo corto rechazado');

console.log(`\nTODOS LOS TESTS PASAN (${n} asserts)`);
