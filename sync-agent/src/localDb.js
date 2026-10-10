import mysql from 'mysql2/promise';
import { config } from './config.js';

let pool = null;

export function localPool() {
  if (!pool) {
    pool = mysql.createPool({
      host: config.db.host,
      port: config.db.port,
      database: config.db.database,
      user: config.db.user,
      password: config.db.password,
      waitForConnections: true,
      connectionLimit: 5,
    });
  }
  return pool;
}

// Lee un pedido local completo para encolarlo al outbox.
export async function fetchLocalPedido(idPedido) {
  const db = localPool();
  const [[pedido]] = await db.query('SELECT * FROM pedidos WHERE id_pedido = ? LIMIT 1', [idPedido]);
  return pedido || null;
}

// Lee una venta local completa (cabecera + detalles) para encolarla al outbox.
export async function fetchLocalVenta(idVenta) {
  const db = localPool();
  const [[venta]] = await db.query('SELECT * FROM venta WHERE id_venta = ? LIMIT 1', [idVenta]);
  if (!venta) return null;
  const [detalles] = await db.query('SELECT * FROM detalle_venta WHERE id_venta = ?', [idVenta]);
  return { ...venta, detalles };
}

export async function localHealth() {
  try {
    const db = localPool();
    await db.query('SELECT 1');
    return true;
  } catch {
    return false;
  }
}
