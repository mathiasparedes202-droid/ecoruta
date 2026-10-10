<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Sync delivery (pedidos) con funciones planas, SIN autoload de Composer.
 *
 * Motivo: el router vivo (backend/index.php) corre en Render con un autoloader
 * que no resuelve las clases App\*; todo lo que el sync delivery necesita
 * (PDO + validaciones) vive aquí con el helper database() (DB_NAME del .env).
 *
 * Contrato (igual que el SyncController de app/):
 *  syncPullPedidos(string $since, int $limit): ['pedidos'=>[], 'historial'=>[], 'max_ts'=>...]
 *  syncPushPedidos(array $items, int $usuarioId): [ ['sync_uuid','status','id_pedido','message'], ... ]
 * Estados por item: applied | duplicado | error.
 * Regla de dinero: el uuid existente responde `duplicado` sin tocar nada,
 * por lo que un pagado=1 jamás se revierte por reintento.
 */

function syncTableExists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $st->execute(['t' => $table]);
    return (bool) $st->fetchColumn();
}

function syncColExists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $st->execute(['t' => $table, 'c' => $col]);
    return (bool) $st->fetchColumn();
}

function syncSystemUserId(PDO $pdo): int
{
    foreach (['usuarios', 'usuario'] as $t) {
        try {
            $row = $pdo->query("SELECT id_usuario FROM `{$t}` ORDER BY id_usuario ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int) $row['id_usuario'];
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    return 0;
}

function syncPullPedidos(string $since, int $limit): array
{
    $pdo = database();
    $limit = max(1, min(500, $limit));
    $ts = strtotime($since);
    $since = $ts === false ? '2000-01-01 00:00:00' : date('Y-m-d H:i:s', $ts);

    if (!syncTableExists($pdo, 'pedidos')) {
        throw new RuntimeException('Esta base no tiene pedidos para sincronizar');
    }

    $tsExpr = syncColExists($pdo, 'pedidos', 'updated_at') ? 'p.updated_at' : 'p.fecha_solicitud';
    $uuidSel = syncColExists($pdo, 'pedidos', 'sync_uuid') ? 'p.sync_uuid' : 'NULL AS sync_uuid';
    $origenSel = syncColExists($pdo, 'pedidos', 'origen') ? 'p.origen' : "'web' AS origen";

    $st = $pdo->prepare(
        "SELECT p.*, {$uuidSel}, {$origenSel}, {$tsExpr} AS _sync_ts
         FROM pedidos p
         WHERE {$tsExpr} > :since
         ORDER BY {$tsExpr} ASC
         LIMIT {$limit}"
    );
    $st->execute(['since' => $since]);
    $pedidos = $st->fetchAll(PDO::FETCH_ASSOC);

    $historial = [];
    $pids = array_column($pedidos, 'id_pedido');
    if ($pids && syncTableExists($pdo, 'historial_estados')) {
        $ph = implode(',', array_fill(0, count($pids), '?'));
        $sth = $pdo->prepare("SELECT * FROM historial_estados WHERE id_pedido IN ($ph) ORDER BY fecha_cambio ASC");
        $sth->execute($pids);
        $historial = $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    $maxTs = $since;
    foreach ($pedidos as $p) {
        if (!empty($p['_sync_ts']) && $p['_sync_ts'] > $maxTs) {
            $maxTs = $p['_sync_ts'];
        }
    }

    return ['pedidos' => $pedidos, 'historial' => $historial, 'max_ts' => $maxTs];
}

function syncPushPedidos(array $items, int $usuarioId): array
{
    $pdo = database();
    if (!syncTableExists($pdo, 'pedidos')) {
        throw new RuntimeException('Esta base no tiene pedidos para sincronizar');
    }
    if ($usuarioId <= 0) {
        $usuarioId = syncSystemUserId($pdo);
    }

    $results = [];
    foreach ($items as $item) {
        $results[] = syncApplyOnePedido($pdo, (array) $item, $usuarioId);
    }
    return $results;
}

function syncApplyOnePedido(PDO $pdo, array $p, int $usuarioId): array
{
    $uuid = trim((string) ($p['sync_uuid'] ?? ''));
    if ($uuid === '' || strlen($uuid) < 8) {
        return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'sync_uuid requerido'];
    }

    try {
        // 1) Idempotencia: ¿ya existe este uuid? Si sí, no se toca nada
        // (así un pagado=1 jamás se revierte por reintento).
        if (syncColExists($pdo, 'pedidos', 'sync_uuid')) {
            $st = $pdo->prepare('SELECT id_pedido, pagado, id_estado FROM pedidos WHERE sync_uuid = :u LIMIT 1');
            $st->execute(['u' => $uuid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'sync_uuid' => $uuid,
                    'status' => 'duplicado',
                    'id_pedido' => (int) $row['id_pedido'],
                    'message' => 'Ya existía (reintento seguro, no se duplicó ni se cobró dos veces)',
                ];
            }
        }

        $origen = trim((string) ($p['direccion_origen'] ?? ''));
        $destino = trim((string) ($p['direccion_destino'] ?? ''));
        if ($origen === '' || $destino === '') {
            return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'Pedido sin dirección de origen o destino'];
        }

        // Comercio: el indicado si existe, si no el primero (igual que createOrder).
        $idComercio = (int) ($p['id_comercio'] ?? 0);
        if ($idComercio > 0) {
            $chk = $pdo->prepare('SELECT 1 FROM comercios WHERE id_comercio = :id LIMIT 1');
            $chk->execute(['id' => $idComercio]);
            if (!$chk->fetchColumn()) {
                $idComercio = 0;
            }
        }
        if ($idComercio <= 0) {
            $idComercio = (int) $pdo->query('SELECT id_comercio FROM comercios ORDER BY id_comercio ASC LIMIT 1')->fetchColumn();
            if ($idComercio <= 0) {
                return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'No hay comercios en el servidor para asignar el pedido'];
            }
        }

        $metodo = (string) ($p['metodo_pago'] ?? 'efectivo');
        if (!in_array($metodo, ['efectivo', 'transferencia', 'mixto'], true)) {
            $metodo = 'efectivo';
        }
        $pagado = (int) ($p['pagado'] ?? 0) === 1 ? 1 : 0;

        $peso = (float) ($p['peso_kg'] ?? 1.5);
        if ($peso <= 0 || $peso > 50) {
            $peso = 1.5;
        }
        $tarifa = max(0, (float) ($p['tarifa_ecologica'] ?? 0));
        if ($tarifa <= 0) {
            return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'Pedido sin tarifa ecológica calculada'];
        }

        // Solo columnas que existan en ESTA base (tolera deriva local/web).
        $vals = [
            'id_comercio' => $idComercio,
            'id_cliente' => isset($p['id_cliente']) && (int) $p['id_cliente'] > 0 ? (int) $p['id_cliente'] : null,
            'id_repartidor' => null,
            'id_estado' => 1,
            'direccion_origen' => $origen,
            'direccion_destino' => $destino,
            'detalle_paquete' => trim((string) ($p['detalle_paquete'] ?? '')) !== '' ? trim((string) $p['detalle_paquete']) : 'Entrega Sustentable EcoRuta',
            'peso_kg' => $peso,
            'alto_cm' => isset($p['alto_cm']) && is_numeric($p['alto_cm']) ? (float) $p['alto_cm'] : null,
            'ancho_cm' => isset($p['ancho_cm']) && is_numeric($p['ancho_cm']) ? (float) $p['ancho_cm'] : null,
            'largo_cm' => isset($p['largo_cm']) && is_numeric($p['largo_cm']) ? (float) $p['largo_cm'] : null,
            'distancia_km' => isset($p['distancia_km']) ? (float) $p['distancia_km'] : null,
            'tarifa_ecologica' => $tarifa,
            'metodo_pago' => $metodo,
            'pagado' => $pagado,
            'fecha_pago' => $pagado ? (string) ($p['fecha_pago'] ?? date('Y-m-d H:i:s')) : null,
            'comprobante_transferencia' => isset($p['comprobante_transferencia']) && trim((string) $p['comprobante_transferencia']) !== '' ? trim((string) $p['comprobante_transferencia']) : null,
            'monto_efectivo' => isset($p['monto_efectivo']) && is_numeric($p['monto_efectivo']) ? (float) $p['monto_efectivo'] : null,
            'monto_transferencia' => isset($p['monto_transferencia']) && is_numeric($p['monto_transferencia']) ? (float) $p['monto_transferencia'] : null,
            'monto_recibido' => isset($p['monto_recibido']) && is_numeric($p['monto_recibido']) ? (float) $p['monto_recibido'] : null,
            'vuelto' => isset($p['vuelto']) && is_numeric($p['vuelto']) ? (float) $p['vuelto'] : null,
            'co2_ahorrado_kg' => isset($p['co2_ahorrado_kg']) ? (float) $p['co2_ahorrado_kg'] : 0,
            'observaciones' => isset($p['observaciones']) ? trim((string) $p['observaciones'] . ' [sync local]') : '[sync local]',
            'fecha_solicitud' => (string) ($p['fecha_solicitud'] ?? date('Y-m-d H:i:s')),
            'destinatario_nombre' => $p['destinatario_nombre'] ?? null,
            'destinatario_telefono' => $p['destinatario_telefono'] ?? null,
            'dest_lat' => isset($p['dest_lat']) && is_numeric($p['dest_lat']) ? (float) $p['dest_lat'] : null,
            'dest_lng' => isset($p['dest_lng']) && is_numeric($p['dest_lng']) ? (float) $p['dest_lng'] : null,
            'sync_uuid' => $uuid,
            'origen' => 'local',
        ];

        $cols = [];
        $phs = [];
        $params = [];
        foreach ($vals as $col => $val) {
            if (!syncColExists($pdo, 'pedidos', $col)) {
                continue;
            }
            $cols[] = "`$col`";
            $phs[] = ":$col";
            $params[$col] = $val;
        }
        if (!$cols) {
            return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'Sin columnas compatibles en pedidos'];
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO pedidos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $phs) . ')');
            $ins->execute($params);
            $idPedido = (int) $pdo->lastInsertId();

            if (syncTableExists($pdo, 'historial_estados') && $usuarioId > 0) {
                $hist = $pdo->prepare(
                    'INSERT INTO historial_estados (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio, observacion)
                     VALUES (:pedido, NULL, 1, :user, :note)'
                );
                $hist->execute([
                    'pedido' => $idPedido,
                    'user' => $usuarioId,
                    'note' => 'Pedido sincronizado desde local (offline)',
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'sync_uuid' => $uuid,
            'status' => 'applied',
            'id_pedido' => $idPedido,
            'message' => 'Pedido aplicado en el servidor',
        ];
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'pagado') !== false) {
            return ['sync_uuid' => $uuid, 'status' => 'conflicto_pago', 'message' => $msg];
        }
        return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => $msg];
    }
}
