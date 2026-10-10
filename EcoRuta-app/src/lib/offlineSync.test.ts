import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  enqueueOp, flushOutbox, readOutbox, pendingCount,
  saveCachedOrders, loadCachedOrders,
} from './offlineSync';
import type { Pedido } from '../types/repartidor';

const order = (id: number, estado = 2): Pedido => ({
  id_pedido: id,
  id_comercio: 1,
  id_estado: estado,
  direccion_origen: 'O',
  direccion_destino: 'D',
  detalle_paquete: 'T',
  sync_uuid: `uuid-test-${id}`,
});

describe('offlineSync (nodo mula)', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.unstubAllGlobals();
  });

  it('cachea pedidos y los devuelve sin red', () => {
    expect(loadCachedOrders()).toBeNull();
    saveCachedOrders([order(1), order(2, 3)]);
    const cached = loadCachedOrders();
    expect(cached?.orders).toHaveLength(2);
    expect(cached?.savedAt).toBeTruthy();
  });

  it('encola y sube en orden al volver la señal', async () => {
    const calls: string[] = [];
    vi.stubGlobal('fetch', vi.fn(async (url: string) => {
      calls.push(String(url));
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    }));
    enqueueOp({ action: 'estado', id_pedido: 1, sync_uuid: 'uuid-test-1', payload: { id_estado: 3 } });
    enqueueOp({ action: 'pago', id_pedido: 1, sync_uuid: 'uuid-test-1', payload: { pagado: true, monto_recibido: 15000 } });
    expect(pendingCount()).toBe(2);
    const r = await flushOutbox('http://x', 'tok', 11);
    expect(r).toMatchObject({ applied: 2, conflicts: 0, stillOffline: false });
    expect(pendingCount()).toBe(0);
    expect(calls).toEqual(['http://x/orders/1/status', 'http://x/orders/1/pago']);
  });

  it('sin red no borra nada y frena en orden', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('Failed to fetch'); }));
    enqueueOp({ action: 'estado', id_pedido: 1, sync_uuid: 'u1', payload: { id_estado: 3 } });
    const r = await flushOutbox('http://x', 'tok', 11);
    expect(r.stillOffline).toBe(true);
    expect(r.applied).toBe(0);
    expect(pendingCount()).toBe(1);
  });

  it('rechazo de negocio (422) va a conflicto, no a reintento eterno', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({
      ok: false, status: 422, json: async () => ({ message: 'Ya entregado' }),
    }) as Response));
    enqueueOp({ action: 'cancel', id_pedido: 9, sync_uuid: 'u9', payload: { motivo: 'motivo largo' } });
    const r = await flushOutbox('http://x', 'tok', 11);
    expect(r.conflicts).toBe(1);
    expect(pendingCount()).toBe(0);
    expect(readOutbox()[0].status).toBe('conflict');
  });

  it('401 frena todo (sesión vencida)', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 401, json: async () => ({}) }) as Response));
    enqueueOp({ action: 'estado', id_pedido: 1, sync_uuid: 'u1', payload: { id_estado: 3 } });
    const r = await flushOutbox('http://x', 'tok', 11);
    expect(r.authExpired).toBe(true);
    expect(pendingCount()).toBe(1);
  });
});
