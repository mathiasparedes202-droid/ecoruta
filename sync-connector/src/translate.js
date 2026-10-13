// Traductor EcoRuta <-> Hub. El hub habla envelopes {uuid,entity,action,payload};
// la API EcoRuta habla {pedidos,ventas,replays}. Sin dependencias.

// Fila/snapshot EcoRuta -> envelope del hub (el uuid ES el sync_uuid).
export function toHubEnvelope(row, entity = 'pedido', action = 'upsert') {
  const uuid = String(row?.sync_uuid || row?.uuid || '');
  if (uuid.length < 8) throw new Error('sync_uuid requerido para el hub');
  return { uuid, entity, action, payload: { ...row }, ts: row?.fecha_solicitud || row?.fecha_emision || new Date().toISOString() };
}

export function toHubBatch(rows, entity = 'pedido', action = 'upsert') {
  return rows.map((r) => toHubEnvelope(r, entity, action));
}

export function toHubReplay({ sync_uuid, action, payload }) {
  if (!sync_uuid || sync_uuid.length < 8) throw new Error('sync_uuid requerido para el hub');
  if (!['pago', 'estado', 'assign', 'cancel', 'editar'].includes(action)) {
    throw new Error('acción desconocida: ' + action);
  }
  return { uuid: sync_uuid, entity: 'pedido_replay', action, payload: { ...(payload || {}) } };
}

// Envelopes del hub -> body del POST /api/sync/push de EcoRuta.
export function toEcorutaPush(items) {
  const pedidos = [];
  const ventas = [];
  const replays = [];
  for (const it of items || []) {
    if (it.entity === 'pedido') pedidos.push({ ...(it.payload || {}), sync_uuid: it.uuid });
    else if (it.entity === 'venta') ventas.push({ ...(it.payload || {}), sync_uuid: it.uuid });
    else if (it.entity === 'pedido_replay') {
      replays.push({ sync_uuid: it.uuid, action: it.action, payload: it.payload || {} });
    }
    // Otras entidades se ignoran aquí (cada sistema traduce las suyas).
  }
  return { pedidos, ventas, replays };
}

// ¿Este resultado ya quedó? (para decidir si el archivo drop se archiva)
export function batchSettled(results) {
  return (results || []).every((r) => ['applied', 'duplicado'].includes(r.status));
}
