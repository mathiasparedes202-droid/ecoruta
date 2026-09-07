<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/geografia.php';
require_once __DIR__ . '/turnos_service.php';
require_once __DIR__ . '/notificaciones_service.php';

/**
 * Tarifas declaradas para el cálculo de la tarifa ecológica.
 */
const TARIFA_KM = 2500;
const TARIFA_KG = 1500;
const TARIFA_DM3 = 250;

function calcularTarifaEcologica(float $tarifaBase, float $distanceKm, float $weightKg, ?float $altoCm, ?float $anchoCm, ?float $largoCm): float
{
    $fee = $tarifaBase;

    if ($distanceKm > 0) {
        $fee += $distanceKm * TARIFA_KM;
    }
    if ($weightKg > 0) {
        $fee += $weightKg * TARIFA_KG;
    }
    if ($altoCm > 0 && $anchoCm > 0 && $largoCm > 0) {
        $volumenDm3 = ($altoCm * $anchoCm * $largoCm) / 1000;
        $fee += $volumenDm3 * TARIFA_DM3;
    }

    return round($fee);
}

/**
 * Lista pedidos. Si llega ?repartidor_id=<id>, devuelve SOLO los pedidos
 * de ese repartidor (sus entregas asignadas, en curso y pasadas). Sin el
 * parámetro se devuelven todos (uso general / admin).
 */
function listOrders(?int $repartidorId = null): array
{
    $sql = 'SELECT p.*, e.nombre_estado, COALESCE(c.razon_social, "Comercio EcoRuta") AS razon_social,
                c.lat AS origen_lat, c.lng AS origen_lng,
                cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono, cl.lat AS cliente_lat, cl.lng AS cliente_lng
         FROM pedidos p
         LEFT JOIN estados_pedido e ON e.id_estado = p.id_estado
         LEFT JOIN comercios c ON c.id_comercio = p.id_comercio
         LEFT JOIN clientes cl ON cl.id_cliente = p.id_cliente';

    if ($repartidorId !== null) {
        $sql .= ' WHERE p.id_repartidor = :repartidor';
    }
    $sql .= ' ORDER BY p.fecha_solicitud DESC';

    $stmt = database()->prepare($sql);
    $stmt->execute($repartidorId !== null ? ['repartidor' => $repartidorId] : []);
    return $stmt->fetchAll();
}

function listOrdersByCommerce(int $commerceId): array
{
    $pdo = database();
    $statement = $pdo->prepare(
        'SELECT p.*, e.nombre_estado, COALESCE(c.razon_social, "Comercio EcoRuta") AS razon_social,
                c.lat AS origen_lat, c.lng AS origen_lng,
                cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono, cl.lat AS cliente_lat, cl.lng AS cliente_lng
         FROM pedidos p
         LEFT JOIN estados_pedido e ON e.id_estado = p.id_estado
         LEFT JOIN comercios c ON c.id_comercio = p.id_comercio
         LEFT JOIN clientes cl ON cl.id_cliente = p.id_cliente
         WHERE p.id_comercio = :commerce
         ORDER BY p.fecha_solicitud DESC'
    );
    $statement->execute(['commerce' => $commerceId]);

    return $statement->fetchAll();
}

function createOrder(array $body): array
{
    requireFields($body, ['direccion_origen', 'direccion_destino']);

    $pdo = database();

    // Obtener un comercio válido por defecto si no se especificó
    $commerceId = isset($body['id_comercio']) && (int) $body['id_comercio'] > 0 
        ? (int) $body['id_comercio'] 
        : (int) $pdo->query("SELECT id_comercio FROM comercios LIMIT 1")->fetchColumn();

    if ($commerceId <= 0) {
        // Crear comercio por defecto si no existe
        $pdo->exec("
            INSERT INTO usuarios (id_rol, correo, contraseña_hash, documento_identidad, nombre_completo, telefono, activo)
            VALUES (1, 'comercio.central@ecoruta.com', '\$2y\$10\$fHbh/jmDwyAu.JyRjibQ3.RxyPO8b5PI4T/cui1nxpr193AFczXwe', '80012345-1', 'EcoComercio Central', '+595 981 123456', TRUE)
            ON DUPLICATE KEY UPDATE nombre_completo = VALUES(nombre_completo)
        ");
        $pdo->exec("
            INSERT INTO comercios (id_usuario, razon_social, ruc, direccion_origen, ciudad, tarifa_base)
            SELECT id_usuario, 'EcoComercio Central', '80012345-1', 'Av. Pinedo 1420', 'Concepción', 12000.00
            FROM usuarios WHERE correo = 'comercio.central@ecoruta.com'
            ON DUPLICATE KEY UPDATE razon_social = VALUES(razon_social)
        ");
        $commerceId = (int) $pdo->query("SELECT id_comercio FROM comercios LIMIT 1")->fetchColumn();
    }

    $distance = isset($body['distancia_km']) ? (float) $body['distancia_km'] : 2.5;
    $co2 = isset($body['co2_ahorrado_kg']) ? (float) $body['co2_ahorrado_kg'] : round($distance * 0.180, 4);
    $detail = !empty($body['detalle_paquete']) ? trim((string) $body['detalle_paquete']) : 'Entrega Sustentable EcoRuta';
    $weight = isset($body['peso_kg']) ? (float) $body['peso_kg'] : 1.5;
    if ($weight <= 0 || $weight > 50) {
        $weight = 1.5;
    }

    // Dimensiones de la caja (cm): opcionales, afectan la tarifa ecológica
    $alto = isset($body['alto_cm']) && is_numeric($body['alto_cm']) ? max(0, (float) $body['alto_cm']) : null;
    $ancho = isset($body['ancho_cm']) && is_numeric($body['ancho_cm']) ? max(0, (float) $body['ancho_cm']) : null;
    $largo = isset($body['largo_cm']) && is_numeric($body['largo_cm']) ? max(0, (float) $body['largo_cm']) : null;
    if (($alto ?: 0) + ($ancho ?: 0) + ($largo ?: 0) === 0) {
        $alto = $ancho = $largo = null;
    }

    // Cliente destinatario: si llega id_cliente se respeta (debe pertenecer al comercio)
    $clienteId = null;
    if (isset($body['id_cliente']) && (int) $body['id_cliente'] > 0) {
        $stmt = database()->prepare(
            'SELECT id_cliente FROM clientes WHERE id_cliente = :id AND id_comercio = :commerce LIMIT 1'
        );
        $stmt->execute(['id' => (int) $body['id_cliente'], 'commerce' => $commerceId]);
        $row = $stmt->fetch();
        if ($row) {
            $clienteId = (int) $row['id_cliente'];
        }
    }

    // El destino debe estar dentro de Paraguay (coordenadas explícitas o extraídas del texto)
    $destino = trim((string) $body['direccion_destino']);
    $destLat = isset($body['dest_lat']) && $body['dest_lat'] !== '' ? (float) $body['dest_lat'] : null;
    $destLng = isset($body['dest_lng']) && $body['dest_lng'] !== '' ? (float) $body['dest_lng'] : null;
    if ($destLat === null || $destLng === null) {
        $coords = extractCoords($destino);
        $destLat = $coords['lat'] ?? null;
        $destLng = $coords['lng'] ?? null;
    }
    if (!coordsInParaguay($destLat, $destLng)) {
        sendJson(['message' => 'El destino de la entrega debe estar dentro de Paraguay'], 422);
    }

    // La tarifa ecológica se calcula de nuevo acá, en el servidor,
    // para que nadie la manipule: caja, peso y distancia siempre cuentan.
    $tarifaBase = (float) database()->query(
        "SELECT COALESCE(tarifa_base, 0) FROM comercios WHERE id_comercio = {$commerceId}"
    )->fetchColumn();
    if ($tarifaBase <= 0) {
        $tarifaBase = 12000;
    }
    $fee = calcularTarifaEcologica($tarifaBase, $distance, $weight, $alto, $ancho, $largo);

    $destinatarioNombre = trim((string) ($body['destinatario_nombre'] ?? ''));
    $destinatarioTelefono = trim((string) ($body['destinatario_telefono'] ?? ''));

    // Método de pago: efectivo (se cobra al entregar), transferencia (se paga
    // por adelantado) o mixto (una parte por transferencia y otra en efectivo).
    $metodoPago = isset($body['metodo_pago']) && in_array($body['metodo_pago'], ['efectivo', 'transferencia', 'mixto'], true)
        ? (string) $body['metodo_pago']
        : 'efectivo';

    $comprobante = isset($body['comprobante_transferencia']) ? trim((string) $body['comprobante_transferencia']) : '';
    $montoEfectivo = null;
    $montoTransferencia = null;

    if ($metodoPago === 'mixto') {
        $montoEfectivo = isset($body['monto_efectivo']) && is_numeric($body['monto_efectivo']) ? (float) $body['monto_efectivo'] : null;
        $montoTransferencia = isset($body['monto_transferencia']) && is_numeric($body['monto_transferencia']) ? (float) $body['monto_transferencia'] : null;
        if ($montoEfectivo === null || $montoTransferencia === null || $montoEfectivo < 0 || $montoTransferencia < 0) {
            sendJson(['message' => 'En el pago mixto indicá cuánto se abona en efectivo y cuánto por transferencia'], 422);
        }
        if (abs($montoEfectivo + $montoTransferencia - $fee) > 1.0) {
            sendJson(['message' => 'Los montos mixtos (efectivo + transferencia) deben sumar la tarifa ecológica'], 422);
        }
        if ($montoTransferencia > 0 && mb_strlen($comprobante) < 5) {
            sendJson(['message' => 'Anotá el número de comprobante de la transferencia (mínimo 5 caracteres)'], 422);
        }
    }

    $pagado = 0;
    if ($metodoPago === 'transferencia') {
        if (filter_var($body['pagado'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            if (mb_strlen($comprobante) < 5) {
                sendJson(['message' => 'Para registrar la transferencia anotá el número del comprobante (mínimo 5 caracteres)'], 422);
            }
            $pagado = 1;
        }
    }
    // El pago mixto nunca nace como pagado: falta cobrar la parte en efectivo al entregar.

    $statement = $pdo->prepare(
        'INSERT INTO pedidos (id_comercio, id_cliente, direccion_origen, direccion_destino, detalle_paquete, peso_kg, alto_cm, ancho_cm, largo_cm, destinatario_nombre, destinatario_telefono, dest_lat, dest_lng, distancia_km, tarifa_ecologica, metodo_pago, pagado, fecha_pago, comprobante_transferencia, monto_efectivo, monto_transferencia, co2_ahorrado_kg, observaciones, fecha_entrega)
         VALUES (:commerce, :cliente, :origin, :destination, :detail, :weight, :alto, :ancho, :largo, :nombre, :telefono, :dlat, :dlng, :distance, :fee, :metodo, :pagado, :fecha_pago, :comprobante, :monto_efectivo, :monto_transferencia, :co2, :notes, NULL)'
    );
    $statement->execute([
        'commerce' => $commerceId,
        'cliente' => $clienteId,
        'origin' => trim((string) $body['direccion_origen']),
        'destination' => $destino,
        'detail' => $detail,
        'weight' => $weight,
        'alto' => $alto,
        'ancho' => $ancho,
        'largo' => $largo,
        'nombre' => $destinatarioNombre ?: null,
        'telefono' => $destinatarioTelefono ?: null,
        'dlat' => $destLat,
        'dlng' => $destLng,
        'distance' => $distance,
        'fee' => $fee,
        'metodo' => $metodoPago,
        'pagado' => $pagado,
        'fecha_pago' => $pagado ? date('Y-m-d H:i:s') : null,
        'comprobante' => $comprobante !== '' ? $comprobante : null,
        'monto_efectivo' => $montoEfectivo,
        'monto_transferencia' => $montoTransferencia,
        'co2' => $co2,
        'notes' => $body['observaciones'] ?? null,
    ]);

    $newId = (int) $pdo->lastInsertId();

    // Registrar en historial_estados
    $userId = isset($body['id_usuario']) ? (int) $body['id_usuario'] : 1;
    $history = $pdo->prepare(
        'INSERT INTO historial_estados (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio, observacion)
         VALUES (:order, NULL, 1, :user, :note)'
    );
    $history->execute([
        'order' => $newId,
        'user' => $userId,
        'note' => 'El comercio registró la entrega y espera asignación',
    ]);

    $detalleCorto = mb_substr((string) $detail, 0, 60);
    $comercioNombre = trim((string) ($pdo->query(
        "SELECT razon_social FROM comercios WHERE id_comercio = {$commerceId}"
    )->fetchColumn() ?? 'Comercio EcoRuta'));
    notifyAdmins(
        'nuevo_pedido',
        'Llegó un pedido nuevo',
        "El comercio «{$comercioNombre}» creó el pedido #{$newId} ({$detalleCorto}). Está esperando que le asignes un repartidor.",
        $newId
    );

    return [
        'id_pedido' => $newId,
        'distancia_km' => $distance,
        'co2_ahorrado_kg' => $co2,
        'tarifa_ecologica' => $fee,
        'metodo_pago' => $metodoPago,
        'pagado' => $pagado,
        'comprobante_transferencia' => $comprobante !== '' ? $comprobante : null,
        'monto_efectivo' => $montoEfectivo,
        'monto_transferencia' => $montoTransferencia,
        'id_cliente' => $clienteId,
        'alto_cm' => $alto,
        'ancho_cm' => $ancho,
        'largo_cm' => $largo,
        'dest_lat' => $destLat,
        'dest_lng' => $destLng,
        'message' => '¡Todo listo! Tu entrega quedó registrada y ya está a la vista del equipo.'
    ];
}

function assignOrderToRepartidor(int $orderId, array $body): array
{
    requireFields($body, ['id_repartidor']);
    $repartidorId = (int) $body['id_repartidor'];

    $pdo = database();

    $repartidor = $pdo->prepare(
        'SELECT r.id_repartidor, u.nombre_completo FROM repartidores r
         JOIN usuarios u ON u.id_usuario = r.id_usuario WHERE r.id_repartidor = :id'
    );
    $repartidor->execute(['id' => $repartidorId]);
    $repartidor = $repartidor->fetch();
    if (!$repartidor) {
        sendJson(['message' => 'Ese repartidor no existe'], 422);
    }

    $pdo->beginTransaction();
    try {
        $current = $pdo->prepare('SELECT id_estado, id_comercio FROM pedidos WHERE id_pedido = :id FOR UPDATE');
        $current->execute(['id' => $orderId]);
        $order = $current->fetch();
        if (!$order) {
            $pdo->rollBack();
            sendJson(['message' => 'No encontramos ese pedido'], 404);
        }

        $previousStatus = (int) $order['id_estado'];
        if ($previousStatus === 4) {
            $pdo->rollBack();
            sendJson(['message' => 'Ese pedido ya fue entregado y no se puede asignar'], 422);
        }
        if ($previousStatus === 3) {
            $pdo->rollBack();
            sendJson(['message' => 'El repartidor ya está en camino con ese pedido; no se puede reasignar'], 422);
        }

        $update = $pdo->prepare(
            'UPDATE pedidos SET id_repartidor = :rep, fecha_asignacion = NOW(), id_estado = 2 WHERE id_pedido = :id'
        );
        $update->execute(['rep' => $repartidorId, 'id' => $orderId]);

        $userId = isset($body['id_usuario_cambio']) ? (int) $body['id_usuario_cambio'] : 1;
        $history = $pdo->prepare(
            'INSERT INTO historial_estados (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio, observacion)
             VALUES (:order, :previous, 2, :user, :note)'
        );
        $history->execute([
            'order' => $orderId,
            'previous' => $previousStatus,
            'user' => $userId,
            'note' => trim((string) ($body['observacion'] ?? '')) ?: 'Se asignó el pedido a un repartidor',
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    notifyRepartidor(
        $repartidorId,
        'pedido_asignado',
        'Te asignaron un pedido',
        "El pedido #{$orderId} quedó asignado a tu recorrido. Entrá a la app para verlo.",
        $orderId
    );

    if (isset($order['id_comercio']) && (int) $order['id_comercio'] > 0) {
        $repartidorNombre = trim((string) ($repartidor['nombre_completo'] ?? 'Un repartidor'));
        notifyComercio(
            (int) $order['id_comercio'],
            'pedido_asignado',
            'Tu pedido ya tiene repartidor',
            "El pedido #{$orderId} fue asignado a {$repartidorNombre}. Entrá a Mis Pedidos para seguirlo.",
            $orderId
        );
    }

    return [
        'message' => 'Pedido asignado. El repartidor ya recibió el aviso.',
        'id_pedido' => $orderId,
        'id_repartidor' => $repartidorId,
        'id_estado' => 2,
    ];
}

function updateOrderStatus(int $orderId, array $body): array
{
    requireFields($body, ['id_estado']);
    $pdo = database();
    $pdo->beginTransaction();

    try {
        $current = $pdo->prepare('SELECT id_estado, id_comercio, id_repartidor FROM pedidos WHERE id_pedido = :id FOR UPDATE');
        $current->execute(['id' => $orderId]);
        $order = $current->fetch();
        if (!$order) {
            $pdo->rollBack();
            sendJson(['message' => 'No encontramos ese pedido'], 404);
        }

        $newStatus = (int) $body['id_estado'];
        if ($newStatus === 5) {
            $pdo->rollBack();
            sendJson(['message' => 'Para cancelar un pedido contanos el motivo desde la opción "Cancelar pedido"'], 422);
        }
        $dateSql = $newStatus === 4 ? ', fecha_entrega = NOW()' : ', fecha_entrega = NULL';
        $update = $pdo->prepare("UPDATE pedidos SET id_estado = :status {$dateSql} WHERE id_pedido = :id");
        $update->execute(['status' => $newStatus, 'id' => $orderId]);

        $userId = isset($body['id_usuario_cambio']) ? (int) $body['id_usuario_cambio'] : 1;
        $history = $pdo->prepare(
            'INSERT INTO historial_estados (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio, observacion)
             VALUES (:order, :previous, :new, :user, :note)'
        );
        $history->execute([
            'order' => $orderId,
            'previous' => $order['id_estado'],
            'new' => $newStatus,
            'user' => $userId,
            'note' => $body['observacion'] ?? null,
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    if ($newStatus === 4 && isset($order['id_comercio']) && (int) $order['id_comercio'] > 0) {
        notifyComercio(
            (int) $order['id_comercio'],
            'pedido_entregado',
            'Tu pedido fue entregado',
            "El pedido #{$orderId} fue entregado al destinatario. Gracias por confiar en EcoRuta.",
            $orderId
        );
    }

    return ['message' => 'Estado del pedido actualizado'];
}

/**
 * Edita un pedido pendiente/cancelado y permite "relanzarlo" (estado 5 -> 1).
 * - Comerciante (rol 1): solo pedidos de su comercio.
 * - Administrador (rol 3): cualquier pedido no entregado.
 * Se recalcula la tarifa ecológica con los datos editados.
 */
function updateOrder(int $orderId, object $claims, array $body): array
{
    $rol = (int) $claims->rol;
    $pdo = database();

    $ownCommerceId = null;
    if ($rol === 1) {
        $stmt = $pdo->prepare('SELECT id_comercio FROM comercios WHERE id_usuario = :user LIMIT 1');
        $stmt->execute(['user' => (int) $claims->sub]);
        $ownCommerceId = $stmt->fetchColumn();
        if ($ownCommerceId === false) {
            sendJson(['message' => 'Tu cuenta no tiene un comercio asociado'], 403);
        }
        $ownCommerceId = (int) $ownCommerceId;
    } elseif ($rol !== 3) {
        sendJson(['message' => 'No tenés permisos para editar pedidos'], 403);
    }

    $pdo->beginTransaction();
    try {
        $current = $pdo->prepare('SELECT * FROM pedidos WHERE id_pedido = :id FOR UPDATE');
        $current->execute(['id' => $orderId]);
        $order = $current->fetch();
        if (!$order) {
            $pdo->rollBack();
            sendJson(['message' => 'Pedido no encontrado'], 404);
        }
        if ($rol === 1 && (int) $order['id_comercio'] !== $ownCommerceId) {
            $pdo->rollBack();
            sendJson(['message' => 'Solo el comercio dueño del pedido puede editarlo'], 403);
        }

        $estadoActual = (int) $order['id_estado'];
        if ($estadoActual === 4) {
            $pdo->rollBack();
            sendJson(['message' => 'Un pedido entregado no se puede editar ni relanzar'], 422);
        }

        $relanzar = filter_var($body['relanzar'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($relanzar && !in_array($estadoActual, [1, 5], true)) {
            $pdo->rollBack();
            sendJson(['message' => 'Solo los pedidos cancelados o pendientes se pueden relanzar'], 422);
        }

        $commerceId = (int) $order['id_comercio'];
        $tarifaBase = (float) $pdo->query(
            "SELECT COALESCE(tarifa_base, 0) FROM comercios WHERE id_comercio = {$commerceId}"
        )->fetchColumn();
        if ($tarifaBase <= 0) {
            $tarifaBase = 12000;
        }

        $destino = isset($body['direccion_destino']) && trim((string) $body['direccion_destino']) !== ''
            ? trim((string) $body['direccion_destino']) : (string) $order['direccion_destino'];
        $detalle = isset($body['detalle_paquete']) && trim((string) $body['detalle_paquete']) !== ''
            ? trim((string) $body['detalle_paquete']) : (string) $order['detalle_paquete'];

        $peso = isset($body['peso_kg']) && $body['peso_kg'] !== ''
            ? (float) $body['peso_kg'] : (float) ($order['peso_kg'] ?? 0);
        if ($peso <= 0 || $peso > 50) {
            $pdo->rollBack();
            sendJson(['message' => 'Peso válido entre 0.1 y 50 kg'], 422);
        }

        $alto = isset($body['alto_cm']) && $body['alto_cm'] !== ''
            ? max(0, (float) $body['alto_cm']) : ($order['alto_cm'] !== null ? (float) $order['alto_cm'] : null);
        $ancho = isset($body['ancho_cm']) && $body['ancho_cm'] !== ''
            ? max(0, (float) $body['ancho_cm']) : ($order['ancho_cm'] !== null ? (float) $order['ancho_cm'] : null);
        $largo = isset($body['largo_cm']) && $body['largo_cm'] !== ''
            ? max(0, (float) $body['largo_cm']) : ($order['largo_cm'] !== null ? (float) $order['largo_cm'] : null);

        $distancia = (float) ($order['distancia_km'] ?? 0);
        $fee = calcularTarifaEcologica($tarifaBase, $distancia, $peso, $alto, $ancho, $largo);

        // Destino dentro de Paraguay (coordenadas nuevas o extraídas del texto)
        $destLat = isset($body['dest_lat']) && $body['dest_lat'] !== ''
            ? (float) $body['dest_lat'] : (float) ($order['dest_lat'] ?? 0);
        $destLng = isset($body['dest_lng']) && $body['dest_lng'] !== ''
            ? (float) $body['dest_lng'] : (float) ($order['dest_lng'] ?? 0);
        if ($destLat === 0.0 || $destLng === 0.0) {
            $coords = extractCoords($destino);
            $destLat = (float) ($coords['lat'] ?? 0);
            $destLng = (float) ($coords['lng'] ?? 0);
        }
        if (!coordsInParaguay($destLat, $destLng)) {
            $pdo->rollBack();
            sendJson(['message' => 'El destino de la entrega debe estar dentro de Paraguay'], 422);
        }

        $sets = [
            'direccion_destino = :destino',
            'detalle_paquete = :detalle',
            'peso_kg = :peso',
            'alto_cm = :alto',
            'ancho_cm = :ancho',
            'largo_cm = :largo',
            'tarifa_ecologica = :fee',
            'dest_lat = :dlat',
            'dest_lng = :dlng',
        ];
        $params = [
            'id' => $orderId,
            'destino' => $destino,
            'detalle' => $detalle,
            'peso' => $peso,
            'alto' => $alto,
            'ancho' => $ancho,
            'largo' => $largo,
            'fee' => round($fee),
            'dlat' => $destLat,
            'dlng' => $destLng,
        ];

        if (isset($body['destinatario_nombre']) && trim((string) $body['destinatario_nombre']) !== '') {
            $sets[] = 'destinatario_nombre = :nombre';
            $params['nombre'] = trim((string) $body['destinatario_nombre']);
        }
        if (isset($body['destinatario_telefono']) && trim((string) $body['destinatario_telefono']) !== '') {
            $sets[] = 'destinatario_telefono = :telefono';
            $params['telefono'] = trim((string) $body['destinatario_telefono']);
        }

        if ($relanzar && $estadoActual === 5) {
            $sets[] = 'id_estado = 1';
            $sets[] = 'id_repartidor = NULL';
            $sets[] = 'fecha_asignacion = NULL';
            $sets[] = 'motivo_cancelacion = NULL';
            $sets[] = 'fecha_cancelacion = NULL';
            $sets[] = 'fecha_entrega = NULL';
        }

        $sqlUpdate = 'UPDATE pedidos SET ' . implode(', ', $sets) . ' WHERE id_pedido = :id';
        $pdo->prepare($sqlUpdate)->execute($params);

        $userId = (int) ($body['id_usuario_cambio'] ?? $claims->sub);
        $historial = $pdo->prepare(
            'INSERT INTO historial_estados (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio, observacion)
             VALUES (:order, :previous, :new, :user, :note)'
        );
        $historial->execute([
            'order' => $orderId,
            'previous' => $estadoActual,
            'new' => $relanzar ? 1 : $estadoActual,
            'user' => $userId,
            'note' => $relanzar
                ? 'El pedido se relanzó y vuelve a estar pendiente de asignación'
                : 'Se editaron los datos del pedido',
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    if ($relanzar) {
        notifyAdmins(
            'pedido_relanzado',
            'Pedido relanzado',
            "El pedido #{$orderId} volvió a lanzarse y quedó pendiente de asignación.",
            $orderId
        );
    }

    return [
        'message' => $relanzar
            ? 'Pedido relanzado. Ya quedó pendiente de asignación nuevamente.'
            : 'Pedido actualizado con la tarifa recalculada.',
        'id_pedido' => $orderId,
        'id_estado' => $relanzar ? 1 : $estadoActual,
        'tarifa_ecologica' => round($fee),
    ];
}

/**
 * Cancela un pedido (estado 5) dejando constancia del motivo.
 * - Repartidor (rol 2): solo pedidos asignados a su recorrido.
 * - Administrador (rol 3): cualquier pedido no entregado.
 * Siempre se notifica a los administradores.
 */
function cancelOrder(int $orderId, object $claims, array $body): array
{
    requireFields($body, ['motivo']);
    $motivo = trim((string) $body['motivo']);
    if (mb_strlen($motivo) < 5) {
        sendJson(['message' => 'Contanos el motivo (mínimo 5 caracteres)'], 422);
    }

    $rol = (int) $claims->rol;
    $repartidorId = null;
    if ($rol === 2) {
        $repartidorId = (int) repartidorDelUsuario((int) $claims->sub)['id_repartidor'];
    } elseif ($rol !== 3) {
        sendJson(['message' => 'No tenés permisos para cancelar pedidos'], 403);
    }

    $pdo = database();
    $pdo->beginTransaction();
    try {
        $current = $pdo->prepare(
            'SELECT id_estado, id_repartidor, id_comercio FROM pedidos WHERE id_pedido = :id FOR UPDATE'
        );
        $current->execute(['id' => $orderId]);
        $order = $current->fetch();
        if (!$order) {
            $pdo->rollBack();
            sendJson(['message' => 'Pedido no encontrado'], 404);
        }

        if ($repartidorId !== null && (int) $order['id_repartidor'] !== $repartidorId) {
            $pdo->rollBack();
            sendJson(['message' => 'Solo podés cancelar pedidos asignados a tu recorrido'], 403);
        }

        $previousStatus = (int) $order['id_estado'];
        if ($previousStatus === 4) {
            $pdo->rollBack();
            sendJson(['message' => 'El pedido ya fue entregado y no se puede cancelar'], 422);
        }
        if ($previousStatus === 5) {
            $pdo->rollBack();
            sendJson(['message' => 'El pedido ya está cancelado'], 422);
        }

        $update = $pdo->prepare(
            'UPDATE pedidos SET id_estado = 5, motivo_cancelacion = :motivo, fecha_cancelacion = NOW(), fecha_entrega = NULL WHERE id_pedido = :id'
        );
        $update->execute(['motivo' => $motivo, 'id' => $orderId]);

        $userId = (int) ($body['id_usuario_cambio'] ?? $claims->sub);
        $history = $pdo->prepare(
            'INSERT INTO historial_estados (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio, observacion)
             VALUES (:order, :previous, 5, :user, :note)'
        );
        $history->execute([
            'order' => $orderId,
            'previous' => $previousStatus,
            'user' => $userId,
            'note' => 'Pedido cancelado: ' . $motivo,
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    $comercioNombre = trim((string) $pdo->query(
        "SELECT COALESCE(c.razon_social, 'Comercio EcoRuta') FROM pedidos p
         LEFT JOIN comercios c ON c.id_comercio = p.id_comercio WHERE p.id_pedido = {$orderId}"
    )->fetchColumn());
    notifyAdmins(
        'pedido_cancelado',
        'Pedido cancelado',
        "El pedido #{$orderId} de «{$comercioNombre}» fue cancelado. Motivo: {$motivo}",
        $orderId
    );

    // Aviso directo al comercio dueño: debe contactar a su cliente
    if (isset($order['id_comercio']) && (int) $order['id_comercio'] > 0) {
        notifyComercio(
            (int) $order['id_comercio'],
            'pedido_cancelado',
            'Tu pedido fue cancelado',
            "El pedido #{$orderId} ({$motivo}) fue cancelado. Contactá a tu cliente para avisarle y, si querés, editalo y volvé a lanzarlo desde Mis Pedidos.",
            $orderId
        );
    }

    return [
        'message' => 'Pedido cancelado. Se notificó al administrador y al comercio.',
        'id_pedido' => $orderId,
        'id_estado' => 5,
        'motivo' => $motivo,
    ];
}

/**
 * Registra la confirmación del pago de un pedido (metodo_pago) y avisa
 * al repartidor por la app si el pedido pasó a pagado.
 * - Comerciante (rol 1): dueño del pedido (ej: confirmó la transferencia).
 * - Administrador (rol 3): cualquier pedido.
 * - Repartidor (rol 2): solo su pedido asignado (cobro en efectivo al entregar).
 */
function updateOrderPayment(int $orderId, object $claims, array $body): array
{
    if (!array_key_exists('pagado', $body)) {
        sendJson(['message' => 'Falta indicar si el pedido está pagado o no'], 422);
    }
    $newPaid = filter_var($body['pagado'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

    $rol = (int) $claims->rol;
    $pdores = database();

    $asignadoA = null;
    $permitido = $rol === 3;
    if ($rol === 1) {
        $stmt = $pdores->prepare('SELECT id_comercio FROM comercios WHERE id_usuario = :user LIMIT 1');
        $stmt->execute(['user' => (int) $claims->sub]);
        $asignadoA = $stmt->fetchColumn();
        $permitido = $asignadoA !== false;
    } elseif ($rol === 2) {
        $asignadoA = (int) repartidorDelUsuario((int) $claims->sub)['id_repartidor'];
        $permitido = true;
    }

    $pdo = database();
    $pdo->beginTransaction();
    try {
        $current = $pdo->prepare(
            'SELECT id_estado, id_comercio, id_repartidor, metodo_pago, pagado, tarifa_ecologica,
                    comprobante_transferencia, monto_efectivo, monto_transferencia
             FROM pedidos WHERE id_pedido = :id FOR UPDATE'
        );
        $current->execute(['id' => $orderId]);
        $order = $current->fetch();
        if (!$order) {
            $pdo->rollBack();
            sendJson(['message' => 'Pedido no encontrado'], 404);
        }

        if (!$permitido) {
            $pdo->rollBack();
            sendJson(['message' => 'No tenés permisos para registrar el pago de este pedido'], 403);
        }
        if ($rol === 1 && (int) $order['id_comercio'] !== (int) $asignadoA) {
            $pdo->rollBack();
            sendJson(['message' => 'Solo el comercio dueño del pedido puede registrar su pago'], 403);
        }
        if ($rol === 2 && (int) $order['id_repartidor'] !== $asignadoA) {
            $pdo->rollBack();
            sendJson(['message' => 'Este pedido no está asignado a tu recorrido'], 403);
        }

        $estado = (int) $order['id_estado'];
        if ($estado === 5) {
            $pdo->rollBack();
            sendJson(['message' => 'Un pedido cancelado no admite pagos'], 422);
        }

        $yaPagado = (int) $order['pagado'] === 1;
        if ($newPaid === 0) {
            if ($yaPagado && $estado === 4) {
                $pdo->rollBack();
                sendJson(['message' => 'El pedido ya fue entregado y pagado; no se puede revertir'], 422);
            }
            $update = $pdo->prepare(
                'UPDATE pedidos SET pagado = 0, fecha_pago = NULL WHERE id_pedido = :id'
            );
            $update->execute(['id' => $orderId]);
            $pdo->commit();
            return ['message' => 'El pedido volvió a quedar como pendiente de pago', 'id_pedido' => $orderId, 'pagado' => 0];
        }

        if (!$yaPagado) {
            $metodo = (string) $order['metodo_pago'];
            $tarifa = (float) $order['tarifa_ecologica'];
            $updates = ['pagado = 1', 'fecha_pago = NOW()'];
            $params = ['id' => $orderId];

            // Transferencia (pura o la parte de pago de un mixto): el comprobante es obligatorio.
            $requiereTransferencia = $metodo === 'transferencia'
                || ($metodo === 'mixto' && (float) ($order['monto_transferencia'] ?? 0) > 0);
            if ($requiereTransferencia) {
                $comprobante = isset($body['comprobante_transferencia']) ? trim((string) $body['comprobante_transferencia']) : '';
                if (mb_strlen($comprobante) < 5) {
                    $pdo->rollBack();
                    sendJson(['message' => 'Confirmar la transferencia requiere el número del comprobante (mínimo 5 caracteres)'], 422);
                }
                $updates[] = 'comprobante_transferencia = :comprobante';
                $params['comprobante'] = $comprobante;
            }

            // Efectivo (puro o la parte en efectivo de un mixto): validar monto recibido y calcular el vuelto.
            $montoEfectivo = $metodo === 'mixto'
                ? (float) ($order['monto_efectivo'] ?? 0)
                : ($metodo === 'efectivo' ? $tarifa : 0);
            if ($montoEfectivo > 0) {
                $montoRecibido = isset($body['monto_recibido']) && is_numeric($body['monto_recibido'])
                    ? (float) $body['monto_recibido']
                    : null;
                if ($montoRecibido === null || $montoRecibido <= 0) {
                    $pdo->rollBack();
                    $legible = number_format($montoEfectivo, 0, ',', '.');
                    sendJson(['message' => "Contanos cuánto efectivo recibió el repartidor para validar el vuelto (corresponde cobrar ₲ {$legible})"], 422);
                }
                if ($montoRecibido < $montoEfectivo) {
                    $pdo->rollBack();
                    $faltante = number_format($montoEfectivo - $montoRecibido, 0, ',', '.');
                    sendJson(['message' => "El efectivo recibido es menor a lo que corresponde; faltan ₲ {$faltante}"], 422);
                }
                $vuelto = round($montoRecibido - $montoEfectivo);
                $updates[] = 'monto_recibido = :monto_recibido';
                $updates[] = 'vuelto = :vuelto';
                $params['monto_recibido'] = $montoRecibido;
                $params['vuelto'] = $vuelto;
            }

            $update = $pdo->prepare('UPDATE pedidos SET ' . implode(', ', $updates) . ' WHERE id_pedido = :id');
            $update->execute($params);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    $metodo = (string) $order['metodo_pago'];
    $metodoLabel = $metodo === 'transferencia' ? 'por transferencia'
        : ($metodo === 'mixto' ? 'mixto (transferencia + efectivo)' : 'en efectivo');
    $repartidorAsignado = isset($order['id_repartidor']) ? (int) $order['id_repartidor'] : 0;

    if ($rol === 2) {
        notifyAdmins(
            'pedido_pagado',
            'Cobro registrado por el repartidor',
            "El repartidor registró el cobro {$metodoLabel} del pedido #{$orderId}.",
            $orderId
        );
    } elseif ($repartidorAsignado > 0) {
        notifyRepartidor(
            $repartidorAsignado,
            'pedido_pagado',
            'Ya se confirmó el pago',
            "El pedido #{$orderId} ya fue pagado {$metodoLabel}. No tenés que cobrarlo al entregar.",
            $orderId
        );
    }

    if (isset($order['id_comercio']) && (int) $order['id_comercio'] > 0) {
        notifyComercio(
            (int) $order['id_comercio'],
            'pedido_pagado',
            'Tu pedido fue pagado',
            "El pedido #{$orderId} quedó marcado como pagado {$metodoLabel}. Ya está al día.",
            $orderId
        );
    }

    return [
        'message' => 'Pago registrado. Cualquiera que vea el pedido ya sabe que está al día.',
        'id_pedido' => $orderId,
        'pagado' => 1,
        'metodo_pago' => $metodo,
        'comprobante_transferencia' => isset($comprobante) ? $comprobante : $order['comprobante_transferencia'],
        'vuelto' => isset($vuelto) ? $vuelto : null,
    ];
}
