// Cola offline del repartidor (nodo mula): si no hay señal, los cambios
// (estado, cobro, cancelación) se guardan en el teléfono con su sync_uuid y
// se suben solos al recuperar señal, en orden FIFO. Idempotencia real:
// - pago: el servidor no re-aplica un pagado=1 (devuelve éxito sin cambios).
// - estado/cancel: pre-chequeos + conflictos visibles si el servidor rechaza.
// Los pedidos asignados se cachean en cada carga exitosa para trabajar sin red.

import type { Pedido } from '../types/repartidor';

export type SyncAction = 'estado' | 'pago' | 'cancel';

export interface QueuedOp {
  id: string;
  action: SyncAction;
  id_pedido: number;
  sync_uuid?: string | null;
  payload: Record<string, unknown>;
  createdAt: string;
  attempts: number;
  status: 'pending' | 'conflict';
  lastError?: string;
}

const ORDERS_KEY = 'ecoruta_mis_pedidos_v1';
const OUTBOX_KEY = 'ecoruta_outbox_v1';

interface CachedOrders {
  savedAt: string;
  orders: Pedido[];
}

function readJson<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}

export function loadCachedOrders(): CachedOrders | null {
  const data = readJson<CachedOrders | null>(ORDERS_KEY, null);
  if (!data || !Array.isArray(data.orders)) return null;
  return data;
}

export function saveCachedOrders(orders: Pedido[]): void {
  try {
    localStorage.setItem(ORDERS_KEY, JSON.stringify({ savedAt: new Date().toISOString(), orders }));
  } catch {
    /* almacenamiento lleno o bloqueado: se sigue sin caché */
  }
}

export function readOutbox(): QueuedOp[] {
  const list = readJson<QueuedOp[]>(OUTBOX_KEY, []);
  return Array.isArray(list) ? list : [];
}

function writeOutbox(items: QueuedOp[]): void {
  try {
    localStorage.setItem(OUTBOX_KEY, JSON.stringify(items));
  } catch {
    /* sin espacio: la cola en memoria se pierde, los cambios ya aplicados localmente no */
  }
}

export function pendingCount(): number {
  return readOutbox().filter((o) => o.status === 'pending').length;
}

export function conflictCount(): number {
  return readOutbox().filter((o) => o.status === 'conflict').length;
}

export function enqueueOp(op: Omit<QueuedOp, 'id' | 'createdAt' | 'attempts' | 'status'>): QueuedOp {
  const items = readOutbox();
  const full: QueuedOp = {
    ...op,
    id: `q_${Date.now()}_${Math.floor(Math.random() * 1e6)}`,
    createdAt: new Date().toISOString(),
    attempts: 0,
    status: 'pending',
  };
  items.push(full);
  writeOutbox(items);
  return full;
}

export function markOp(id: string, patch: Partial<QueuedOp>): void {
  writeOutbox(readOutbox().map((o) => (o.id === id ? { ...o, ...patch } : o)));
}

export function removeOp(id: string): void {
  writeOutbox(readOutbox().filter((o) => o.id !== id));
}

export function isNetworkError(error: unknown): boolean {
  // fetch fallido = sin ruta al servidor (sin internet / servidor caído).
  return error instanceof TypeError;
}

interface FlushResult {
  applied: number;
  conflicts: number;
  stillOffline: boolean;
  authExpired: boolean;
}

function endpointFor(op: QueuedOp): string {
  if (op.action === 'pago') return `/orders/${op.id_pedido}/pago`;
  if (op.action === 'cancel') return `/orders/${op.id_pedido}/cancel`;
  return `/orders/${op.id_pedido}/status`;
}

function bodyFor(op: QueuedOp, userId: number): Record<string, unknown> {
  if (op.action === 'estado') {
    return { id_estado: op.payload.id_estado, observacion: op.payload.observacion, id_usuario_cambio: userId };
  }
  if (op.action === 'pago') {
    return { ...op.payload };
  }
  return { ...op.payload, id_usuario_cambio: userId };
}

// Sube la cola en orden. Nunca borra en caso de duda: solo remueve con 2xx.
// 401 -> sesión muerta (frena todo). 4xx de regla de negocio -> conflicto visible.
export async function flushOutbox(apiBase: string, token: string | null, userId: number): Promise<FlushResult> {
  const result: FlushResult = { applied: 0, conflicts: 0, stillOffline: false, authExpired: false };
  if (!token) return result;
  const items = readOutbox().filter((o) => o.status === 'pending');
  for (const op of items) {
    let response: Response;
    try {
      response = await fetch(`${apiBase}${endpointFor(op)}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify(bodyFor(op, userId)),
      });
    } catch (error) {
      if (isNetworkError(error)) {
        result.stillOffline = true;
        return result; // sin red otra vez: se conserva el orden, se reintenta luego
      }
      throw error;
    }
    if (response.ok) {
      removeOp(op.id);
      result.applied++;
      continue;
    }
    if (response.status === 401) {
      result.authExpired = true;
      return result;
    }
    const body = await response.json().catch(() => ({} as { message?: string }));
    const msg = String(body.message || `El servidor rechazó el cambio (${response.status})`);
    // 422/409/403/404 = regla de negocio o dato viejo: a revisión manual, no reintento eterno.
    if ([403, 404, 409, 422].includes(response.status)) {
      markOp(op.id, { status: 'conflict', lastError: msg, attempts: op.attempts + 1 });
      result.conflicts++;
      continue;
    }
    // 500 u otros: se deja pendiente para el próximo ciclo.
    markOp(op.id, { attempts: op.attempts + 1, lastError: msg });
  }
  return result;
}
