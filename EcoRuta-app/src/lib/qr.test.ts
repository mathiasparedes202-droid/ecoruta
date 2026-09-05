import { describe, expect, it } from 'vitest';
import { buildOrderQrPayload, decodeQrFromDataUrl, generateQrDataUrl, isValidOrderQr, parseOrderQrPayload } from './qr';

describe('QR de pedidos EcoRuta', () => {
  it('genera un payload estable para un pedido', () => {
    expect(buildOrderQrPayload({ id_pedido: 42, id_repartidor: 7 })).toContain('ecoruta:pedido:42');
    expect(buildOrderQrPayload({ id_pedido: 42, id_repartidor: 7 })).toContain('repartidor=7');
  });

  it('genera y decodifica un codigo qr para un pedido', async () => {
    const payload = buildOrderQrPayload({ id_pedido: 42, id_repartidor: 7 });
    const dataUrl = await generateQrDataUrl(payload);

    expect(dataUrl).toMatch(/^data:image\/png;base64,/);
    expect(await decodeQrFromDataUrl(dataUrl)).toBe(payload);
  }, 20000);

  it('valida que el qr corresponda al pedido esperado', () => {
    const payload = buildOrderQrPayload({ id_pedido: 42, id_repartidor: 7 });
    expect(parseOrderQrPayload(payload)).toEqual({ idPedido: 42, idRepartidor: 7 });
    expect(isValidOrderQr(payload, 42)).toBe(true);
    expect(isValidOrderQr(payload, 99)).toBe(false);
    expect(isValidOrderQr('texto-sin-formato', 42)).toBe(false);
  });
});