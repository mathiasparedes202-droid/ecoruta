<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Sprint 4 — Facturación legal delivery (SET/DNIT, nivel pre-fiscal).
 *
 * Alcance honesto: genera comprobantes con timbrado vigente controlado,
 * folio único EEE-PPP-NNNNNNN, IVA discriminado y QR de verificación con
 * hash de control interno (CDC). La homologación en línea SIFEN (CDC oficial
 * y autorización SET en tiempo real) queda como fase 2: requiere certificado
 * y credenciales del ambiente de testing de la SET.
 */

// ---------- Timbrados ----------

function timbradoVigente(PDO $pdo): ?array
{
    $st = $pdo->prepare(
        "SELECT * FROM timbrados
         WHERE activo = 1 AND fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()
           AND secuencia_actual < nro_fin
         ORDER BY fecha_fin ASC LIMIT 1"
    );
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function timbradoAlerta(PDO $pdo, ?array $timbrado = null): array
{
    $timbrado = $timbrado ?? timbradoVigente($pdo);
    if (!$timbrado) {
        return ['vigente' => false, 'dias_restantes' => 0, 'folios_restantes' => 0, 'mensaje' => 'Sin timbrado vigente: la emisión está bloqueada'];
    }
    $dias = (int) $pdo->query(
        "SELECT DATEDIFF('" . $timbrado['fecha_fin'] . "', CURDATE())"
    )->fetchColumn();
    $folios = (int) $timbrado['nro_fin'] - (int) $timbrado['secuencia_actual'];
    $avisos = [];
    if ($dias < 15) {
        $avisos[] = "El timbrado vence en $dias día(s)";
    }
    if ($folios < 50) {
        $avisos[] = "Quedan $folios folio(s) disponibles";
    }
    return ['vigente' => true, 'dias_restantes' => $dias, 'folios_restantes' => $folios, 'mensaje' => implode('. ', $avisos)];
}

function crearTimbrado(array $body): array
{
    foreach (['ruc', 'razon_social', 'num_timbrado', 'fecha_inicio', 'fecha_fin', 'nro_fin'] as $f) {
        if (!isset($body[$f]) || trim((string) $body[$f]) === '') {
            sendJson(['message' => "Falta completar: $f"], 422);
        }
    }
    if (strtotime((string) $body['fecha_fin']) < strtotime((string) $body['fecha_inicio'])) {
        sendJson(['message' => 'La vigencia termina antes de empezar'], 422);
    }
    $pdo = database();
    $st = $pdo->prepare(
        'INSERT INTO timbrados (ruc, razon_social, direccion, num_timbrado, establecimiento, punto_expedicion,
          fecha_inicio, fecha_fin, nro_inicio, nro_fin, secuencia_actual, activo)
         VALUES (:ruc, :rs, :dir, :num, :est, :pto, :ini, :fin, :nini, :nfin, :sec, :act)'
    );
    try {
        $st->execute([
            'ruc' => trim((string) $body['ruc']),
            'rs' => trim((string) $body['razon_social']),
            'dir' => isset($body['direccion']) ? trim((string) $body['direccion']) : null,
            'num' => trim((string) $body['num_timbrado']),
            'est' => substr(preg_replace('/\D/', '', (string) ($body['establecimiento'] ?? '001')) . '000', 0, 3),
            'pto' => substr(preg_replace('/\D/', '', (string) ($body['punto_expedicion'] ?? '001')) . '000', 0, 3),
            'ini' => $body['fecha_inicio'],
            'fin' => $body['fecha_fin'],
            'nini' => max(1, (int) ($body['nro_inicio'] ?? 1)),
            'nfin' => (int) $body['nro_fin'],
            'sec' => max(0, (int) ($body['nro_inicio'] ?? 1) - 1),
            'act' => array_key_exists('activo', $body) ? ((int) $body['activo'] ? 1 : 0) : 1,
        ]);
    } catch (Throwable $e) {
        sendJson(['message' => 'No se pudo registrar el timbrado (¿número duplicado o rango inválido?)'], 422);
    }
    return ['id_timbrado' => (int) $pdo->lastInsertId(), 'message' => 'Timbrado registrado'];
}

function listarTimbrados(): array
{
    return database()->query('SELECT * FROM timbrados ORDER BY activo DESC, fecha_fin DESC')->fetchAll(PDO::FETCH_ASSOC);
}

function actualizarTimbrado(int $id, array $body): array
{
    $pdo = database();
    $sets = [];
    $params = ['id' => $id];
    foreach (['fecha_inicio' => 'fecha_inicio', 'fecha_fin' => 'fecha_fin', 'activo' => 'activo', 'nro_fin' => 'nro_fin', 'direccion' => 'direccion', 'razon_social' => 'razon_social'] as $in => $col) {
        if (array_key_exists($in, $body)) {
            $sets[] = "$col = :$in";
            $params[$in] = $in === 'activo' ? ((int) $body[$in] ? 1 : 0) : $body[$in];
        }
    }
    if (!$sets) {
        sendJson(['message' => 'Nada para actualizar'], 422);
    }
    try {
        $pdo->prepare('UPDATE timbrados SET ' . implode(', ', $sets) . ' WHERE id_timbrado = :id')->execute($params);
    } catch (Throwable $e) {
        sendJson(['message' => 'No se pudo actualizar el timbrado'], 422);
    }
    return ['message' => 'Timbrado actualizado'];
}

// ---------- Emisión ----------

function calcularIvaIncluido(float $total): array
{
    // Precios con IVA incluido, servicio 10%: iva = total * 10 / 110.
    $iva10 = round($total * 10 / 110, 2);
    return ['gravada_10' => round($total - $iva10, 2), 'iva_10' => $iva10];
}

/**
 * Emite la factura legal de un pedido. Idempotente: un pedido genera como
 * máximo una factura (UNIQUE id_pedido); el reintento devuelve la existente.
 * Bloquea si no hay timbrado vigente o se agotó la numeración.
 *
 * $json=true (endpoints): los errores salen como respuesta HTTP y terminan.
 * $json=false (auto-factura interna): los errores son excepciones capturables
 * para no romper el flujo que la invoca.
 */
function emitirFacturaPorPedido(int $idPedido, string $origen = 'manual', int $usuarioId = 0, array $receptor = [], bool $json = true): array
{
    $fail = function (string $message, int $code) use ($json): never {
        if ($json) {
            sendJson(['message' => $message], $code);
        }
        throw new RuntimeException($message, $code);
    };
    $pdo = database();
    $pdo->beginTransaction();
    try {
        $ex = $pdo->prepare('SELECT id_factura, numero_completo, estado FROM facturas WHERE id_pedido = :id LIMIT 1');
        $ex->execute(['id' => $idPedido]);
        $previa = $ex->fetch(PDO::FETCH_ASSOC);
        if ($previa) {
            $pdo->rollBack();
            return ['id_factura' => (int) $previa['id_factura'], 'numero' => $previa['numero_completo'], 'repetida' => true, 'message' => 'El pedido ya tenía factura (no se duplicó)'];
        }

        $ped = $pdo->prepare('SELECT * FROM pedidos WHERE id_pedido = :id LIMIT 1');
        $ped->execute(['id' => $idPedido]);
        $p = $ped->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            $pdo->rollBack();
            $fail('Pedido no encontrado', 404);
        }
        if ((int) $p['id_estado'] === 5) {
            $pdo->rollBack();
            $fail('Un pedido cancelado no se factura', 422);
        }

        $tim = $pdo->prepare(
            "SELECT * FROM timbrados
             WHERE activo = 1 AND fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()
               AND secuencia_actual < nro_fin
             ORDER BY fecha_fin ASC LIMIT 1 FOR UPDATE"
        );
        $tim->execute();
        $t = $tim->fetch(PDO::FETCH_ASSOC);
        if (!$t) {
            $pdo->rollBack();
            $fail('Sin timbrado vigente o numeración agotada: emisión bloqueada', 422);
        }
        $seq = (int) $t['secuencia_actual'] + 1;
        $numero = sprintf('%03d-%03d-%07d', $t['establecimiento'], $t['punto_expedicion'], $seq);
        $pdo->prepare('UPDATE timbrados SET secuencia_actual = :sec WHERE id_timbrado = :id')
            ->execute(['sec' => $seq, 'id' => $t['id_timbrado']]);

        $total = round((float) $p['tarifa_ecologica'], 2);
        if ($total <= 0) {
            $pdo->rollBack();
            $fail('El pedido no tiene tarifa para facturar', 422);
        }
        $iva = calcularIvaIncluido($total);

        // Receptor: override manual > cliente registrado > destinatario > mostrador.
        $rucRec = trim((string) ($receptor['ruc'] ?? ''));
        $rsRec = trim((string) ($receptor['razon_social'] ?? ''));
        $dirRec = trim((string) ($receptor['direccion'] ?? ''));
        if ($rucRec === '') {
            if (!empty($p['id_cliente'])) {
                // Tolera esquemas de clientes distintos (local vs web).
                $cols = $pdo->query('SHOW COLUMNS FROM clientes')->fetchAll(PDO::FETCH_COLUMN);
                $sel = ['id_cliente'];
                foreach (['documento_identidad' => 'documento', 'nombresyapellidos' => 'nombre_rs', 'nombre' => 'nombre_corto', 'direccion' => 'dir', 'ruc_ci' => 'ruc_alt'] as $real => $alias) {
                    if (in_array($real, $cols, true)) {
                        $sel[] = "`$real` AS `$alias`";
                    }
                }
                $cli = $pdo->prepare('SELECT ' . implode(', ', $sel) . ' FROM clientes WHERE id_cliente = :id LIMIT 1');
                $cli->execute(['id' => (int) $p['id_cliente']]);
                if ($c = $cli->fetch(PDO::FETCH_ASSOC)) {
                    $rucRec = trim((string) ($c['documento'] ?? $c['ruc_alt'] ?? ''));
                    $rsRec = $rsRec !== '' ? $rsRec : trim((string) ($c['nombre_rs'] ?? $c['nombre_corto'] ?? ''));
                    $dirRec = $dirRec !== '' ? $dirRec : trim((string) ($c['dir'] ?? ''));
                }
            }
            if ($rsRec === '') {
                $rsRec = trim((string) ($p['destinatario_nombre'] ?? '')) !== '' ? trim((string) $p['destinatario_nombre']) : 'Consumidor Final';
            }
        }
        if ($rsRec === '') {
            $rsRec = 'Consumidor Final';
        }

        $condicion = in_array($receptor['condicion_venta'] ?? 'Contado', ['Contado', 'Crédito'], true)
            ? (string) ($receptor['condicion_venta'] ?? 'Contado') : 'Contado';
        $fecha = date('Y-m-d H:i:s');
        $cdc = hash_hmac('sha256', implode('|', [$t['num_timbrado'], $numero, $t['ruc'], $rucRec, number_format($total, 2, '.', ''), $fecha, $idPedido]), (string) $t['num_timbrado']);
        $qr = implode('|', ['ECORUTA', $t['ruc'], $t['num_timbrado'], $numero, number_format($total, 2, '.', ''), substr($cdc, 0, 16)]);

        $ins = $pdo->prepare(
            "INSERT INTO facturas (id_pedido, id_timbrado, numero_completo, ruc_emisor, razon_social_emisor,
              ruc_receptor, razon_social_receptor, direccion_receptor, condicion_venta, total,
              gravada_10, iva_10, gravada_5, iva_5, exenta, cdc, qr_texto, estado, fecha_emision, origen)
             VALUES (:ped, :tim, :num, :rucE, :rsE, :rucR, :rsR, :dirR, :cond, :tot,
              :g10, :i10, 0, 0, 0, :cdc, :qr, 'Vigente', :fec, :org)"
        );
        $ins->execute([
            'ped' => $idPedido, 'tim' => $t['id_timbrado'], 'num' => $numero,
            'rucE' => $t['ruc'], 'rsE' => $t['razon_social'],
            'rucR' => $rucRec !== '' ? $rucRec : null, 'rsR' => $rsRec, 'dirR' => $dirRec !== '' ? $dirRec : null,
            'cond' => $condicion, 'tot' => $total,
            'g10' => $iva['gravada_10'], 'i10' => $iva['iva_10'],
            'cdc' => $cdc, 'qr' => $qr, 'fec' => $fecha, 'org' => in_array($origen, ['auto', 'manual', 'sync'], true) ? $origen : 'manual',
        ]);
        $idFactura = (int) $pdo->lastInsertId();

        $detalle = 'Servicio de reparto ecológico EcoRuta — pedido #' . $idPedido . ' (' . trim((string) ($p['detalle_paquete'] ?? '')) . ')';
        $pdo->prepare(
            'INSERT INTO factura_items (id_factura, concepto, cantidad, precio_unitario, subtotal, iva_tipo)
             VALUES (:fac, :con, 1, :pre, :sub, 10)'
        )->execute(['fac' => $idFactura, 'con' => mb_substr($detalle, 0, 200), 'pre' => $total, 'sub' => $total]);

        $pdo->prepare(
            "INSERT INTO factura_auditoria (id_factura, accion, id_usuario, detalle) VALUES (:fac, 'emitida', :usr, :det)"
        )->execute(['fac' => $idFactura, 'usr' => $usuarioId > 0 ? $usuarioId : null, 'det' => "Origen $origen, timbrado " . $t['num_timbrado']]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['id_factura' => $idFactura, 'numero' => $numero, 'repetida' => false, 'total' => $total, 'iva_10' => $iva['iva_10'], 'message' => "Factura $numero emitida"];
}

// ---------- Anulación / historial ----------

function anularFactura(int $idFactura, string $motivo, int $usuarioId = 0): array
{
    $motivo = trim($motivo);
    if (mb_strlen($motivo) < 5) {
        sendJson(['message' => 'Indicá el motivo de anulación (mínimo 5 caracteres)'], 422);
    }
    $pdo = database();
    $st = $pdo->prepare('SELECT estado FROM facturas WHERE id_factura = :id LIMIT 1');
    $st->execute(['id' => $idFactura]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendJson(['message' => 'Factura no encontrada'], 404);
    }
    if ($row['estado'] === 'Anulada') {
        sendJson(['message' => 'La factura ya está anulada'], 422);
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE facturas SET estado = 'Anulada', motivo_anulacion = :mot, fecha_anulacion = NOW() WHERE id_factura = :id")
            ->execute(['mot' => mb_substr($motivo, 0, 255), 'id' => $idFactura]);
        $pdo->prepare("INSERT INTO factura_auditoria (id_factura, accion, id_usuario, detalle) VALUES (:fac, 'anulada', :usr, :det)")
            ->execute(['fac' => $idFactura, 'usr' => $usuarioId > 0 ? $usuarioId : null, 'det' => mb_substr($motivo, 0, 255)]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['message' => 'Factura anulada. Queda registrada con su motivo (trazabilidad legal).'];
}

function historialFacturas(array $f): array
{
    $pdo = database();
    $where = [];
    $params = [];
    if (!empty($f['desde'])) {
        $where[] = 'f.fecha_emision >= :desde';
        $params['desde'] = $f['desde'] . ' 00:00:00';
    }
    if (!empty($f['hasta'])) {
        $where[] = 'f.fecha_emision <= :hasta';
        $params['hasta'] = $f['hasta'] . ' 23:59:59';
    }
    if (!empty($f['estado']) && in_array($f['estado'], ['Vigente', 'Anulada'], true)) {
        $where[] = 'f.estado = :estado';
        $params['estado'] = $f['estado'];
    }
    if (!empty($f['numero'])) {
        $where[] = 'f.numero_completo LIKE :numero';
        $params['numero'] = '%' . trim((string) $f['numero']) . '%';
    }
    if (!empty($f['id_comercio'])) {
        $where[] = 'p.id_comercio = :comercio';
        $params['comercio'] = (int) $f['id_comercio'];
    }
    $limit = max(1, min(500, (int) ($f['limit'] ?? 100)));
    $sql = 'SELECT f.*, p.id_comercio, p.direccion_destino
            FROM facturas f JOIN pedidos p ON p.id_pedido = f.id_pedido';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY f.id_factura DESC LIMIT $limit";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function facturaConItems(int $idFactura): ?array
{
    $pdo = database();
    $st = $pdo->prepare('SELECT * FROM facturas WHERE id_factura = :id LIMIT 1');
    $st->execute(['id' => $idFactura]);
    $fac = $st->fetch(PDO::FETCH_ASSOC);
    if (!$fac) {
        return null;
    }
    $it = $pdo->prepare('SELECT * FROM factura_items WHERE id_factura = :id ORDER BY id_item');
    $it->execute(['id' => $idFactura]);
    $fac['items'] = $it->fetchAll(PDO::FETCH_ASSOC);
    $au = $pdo->prepare('SELECT * FROM factura_auditoria WHERE id_factura = :id ORDER BY fecha');
    $au->execute(['id' => $idFactura]);
    $fac['auditoria'] = $au->fetchAll(PDO::FETCH_ASSOC);
    return $fac;
}
