<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/../services/facturacion_service.php';

function soloAdmin(object $claims): void
{
    if ((int) ($claims->rol ?? 0) !== 3) {
        sendJson(['message' => 'Solo un administrador gestiona timbrados'], 403);
    }
}

function listarTimbradosController(object $claims): never
{
    soloAdmin($claims);
    sendJson(listarTimbrados());
}

function crearTimbradoController(object $claims): never
{
    soloAdmin($claims);
    sendJson(crearTimbrado(requestBody()), 201);
}

function actualizarTimbradoController(object $claims, int $id): never
{
    soloAdmin($claims);
    sendJson(actualizarTimbrado($id, requestBody()));
}

function timbradoVigenteController(): never
{
    $pdo = database();
    $t = timbradoVigente($pdo);
    sendJson(['timbrado' => $t, 'alerta' => timbradoAlerta($pdo, $t)]);
}

function emitirFacturaController(object $claims, int $orderId): never
{
    $body = requestBody();
    $receptor = [
        'ruc' => $body['ruc_receptor'] ?? '',
        'razon_social' => $body['razon_social_receptor'] ?? '',
        'direccion' => $body['direccion_receptor'] ?? '',
        'condicion_venta' => $body['condicion_venta'] ?? 'Contado',
    ];
    sendJson(emitirFacturaPorPedido($orderId, 'manual', (int) ($claims->sub ?? 0), $receptor), 201);
}

function historialFacturasController(): never
{
    sendJson(historialFacturas($_GET));
}

function verFacturaController(int $id): never
{
    $fac = facturaConItems($id);
    if (!$fac) {
        sendJson(['message' => 'Factura no encontrada'], 404);
    }
    sendJson($fac);
}

function anularFacturaController(object $claims, int $id): never
{
    $body = requestBody();
    sendJson(anularFactura($id, (string) ($body['motivo'] ?? ''), (int) ($claims->sub ?? 0)));
}

function kudeFacturaController(int $id): never
{    $fac = facturaConItems($id);
    if (!$fac) {
        sendJson(['message' => 'Factura no encontrada'], 404);
    }
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode((string) $fac['qr_texto']);
    $rows = '';
    foreach (($fac['items'] ?? []) as $it) {
        $rows .= '<tr><td>' . htmlspecialchars((string) $it['concepto']) . '</td><td style="text-align:right">'
            . htmlspecialchars((string) $it['cantidad']) . '</td><td style="text-align:right">₲ '
            . number_format((float) $it['precio_unitario'], 0, ',', '.') . '</td><td style="text-align:right">₲ '
            . number_format((float) $it['subtotal'], 0, ',', '.') . '</td></tr>';
    }
    $f = fn($v) => '₲ ' . number_format((float) $v, 0, ',', '.');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>KuDE ' . htmlspecialchars((string) $fac['numero_completo'])
        . '</title><style>body{font-family:Arial,sans-serif;max-width:760px;margin:24px auto;color:#111}table{width:100%;border-collapse:collapse}td,th{border:1px solid #999;padding:6px;font-size:13px}.head{display:flex;justify-content:space-between;gap:16px}.badge{display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:bold;background:' . ($fac['estado'] === 'Vigente' ? '#dcfce7' : '#fee2e2') . '}.muted{color:#555;font-size:12px}@media print{.noprint{display:none}}</style></head><body>'
        . '<div class="head"><div><h2>EcoRuta S.A. — KuDE</h2><p class="muted">Documento pre-fiscal generado por el sistema (fase SIFEN en homologación).</p></div>'
        . '<div style="text-align:center"><img src="' . $qrUrl . '" alt="QR" width="180" height="180"><br><small>' . htmlspecialchars((string) $fac['qr_texto']) . '</small></div></div>'
        . '<p><strong>Factura:</strong> ' . htmlspecialchars((string) $fac['numero_completo'])
        . ' <span class="badge">' . htmlspecialchars((string) $fac['estado']) . '</span><br>'
        . '<strong>Timbrado:</strong> ' . htmlspecialchars((string) $fac['ruc_emisor']) . ' · '
        . htmlspecialchars((string) $fac['razon_social_emisor']) . '<br>'
        . '<strong>Receptor:</strong> ' . htmlspecialchars((string) ($fac['razon_social_receptor'] ?? '')) . ' — RUC/CI: '
        . htmlspecialchars((string) ($fac['ruc_receptor'] ?? '—')) . '<br>'
        . '<strong>Emisión:</strong> ' . htmlspecialchars((string) $fac['fecha_emision']) . ' · <strong>CDC (control interno):</strong> <small>' . htmlspecialchars(substr((string) $fac['cdc'], 0, 24)) . '…</small></p>'
        . '<table><tr><th>Concepto</th><th>Cant.</th><th>P. unitario</th><th>Subtotal</th></tr>' . $rows . '</table>'
        . '<p style="text-align:right"><strong>Total: ' . $f($fac['total_factura'] ?? $fac['total']) . '</strong><br>'
        . '<span class="muted">Gravada 10%: ' . $f($fac['gravada_10']) . ' · IVA 10%: ' . $f($fac['iva_10']) . '</span></p>'
        . '<p class="muted noprint">Usá Imprimir del navegador para guardar en PDF.</p>'
        . '</body></html>';
    exit;
}

// ---- Motor factura-py (modelo del zip) para HTML/PDF ----
// Construye el modelo normalizado que espera generate-factura.js
// ({idVenta, emisor, factura, cliente, detalles[], resumen{}})
// con datos REALES del timbrado y el pedido (sin hardcodes).
function buildDeliveryFacturaModel(int $idFactura): array
{
    $fac = facturaConItems($idFactura);
    if (!$fac) {
        sendJson(['message' => 'Factura no encontrada'], 404);
    }
    $pdo = database();
    $tim = $pdo->prepare('SELECT * FROM timbrados WHERE id_timbrado = :id LIMIT 1');
    $tim->execute(['id' => (int) $fac['id_timbrado']]);
    $t = $tim->fetch(PDO::FETCH_ASSOC) ?: [];
    $ped = $pdo->prepare('SELECT metodo_pago FROM pedidos WHERE id_pedido = :id LIMIT 1');
    $ped->execute(['id' => (int) $fac['id_pedido']]);
    $metodoPago = $ped->fetchColumn() ?: 'efectivo';

    $total = (float) $fac['total'];
    $detalles = array_map(function ($it) {
        $sub = (float) ($it['subtotal'] ?? 0);
        $iva = (int) ($it['iva_tipo'] ?? 10);
        return [
            'idDetalleVenta' => $it['id_item'] ?? null,
            'idProducto' => null,
            'cantidad' => (float) ($it['cantidad'] ?? 1),
            'unidadMedida' => 'Servicio',
            'descripcion' => $it['concepto'] ?? 'Servicio de reparto',
            'precioUnitario' => (float) ($it['precio_unitario'] ?? 0),
            'tipoIva' => $iva,
            'exenta' => $iva === 0 ? $sub : 0,
            'iva5' => $iva === 5 ? $sub : 0,
            'iva10' => $iva === 10 ? $sub : 0,
            'subtotal' => $sub,
        ];
    }, $fac['items'] ?? []);

    return [
        'idVenta' => (int) $fac['id_factura'],
        'idPedido' => (int) $fac['id_pedido'],
        'emisor' => [
            'razonSocial' => $fac['razon_social_emisor'] ?? ($t['razon_social'] ?? 'EcoRuta S.A.'),
            'ruc' => $fac['ruc_emisor'] ?? ($t['ruc'] ?? ''),
            'direccion' => $t['direccion'] ?? '',
            'telefono' => '',
            'email' => '',
            'timbrado' => $t['num_timbrado'] ?? '',
            'vigenciaTimbrado' => $t['fecha_fin'] ?? '',
            'establecimiento' => $t['establecimiento'] ?? '001',
            'puntoExpedicion' => $t['punto_expedicion'] ?? '001',
        ],
        'factura' => [
            'numeroFactura' => $fac['numero_completo'],
            'numeroComprobante' => null,
            'tipoComprobante' => 'Factura',
            'timbrado' => $t['num_timbrado'] ?? '',
            'fechaEmision' => $fac['fecha_emision'] ?? null,
            'estado' => $fac['estado'] ?? 'Vigente',
            'condicionVenta' => $fac['condicion_venta'] ?? 'Contado',
            'metodoPago' => ucfirst((string) $metodoPago),
            'plazo' => null,
            'observaciones' => $fac['motivo_anulacion'] ?? null,
        ],
        'cliente' => [
            'idCliente' => null,
            'razonSocial' => $fac['razon_social_receptor'] ?? 'Consumidor Final',
            'rucCi' => $fac['ruc_receptor'] ?? 'Sin documento',
            'celular' => null,
            'direccion' => $fac['direccion_receptor'] ?? '',
        ],
        'detalles' => $detalles,
        'resumen' => [
            'subtotalExenta' => (float) ($fac['exenta'] ?? 0),
            'subtotalIva5' => (float) ($fac['gravada_5'] ?? 0),
            'subtotalIva10' => (float) ($fac['gravada_10'] ?? 0),
            'iva5' => (float) ($fac['iva_5'] ?? 0),
            'iva10' => (float) ($fac['iva_10'] ?? 0),
            'totalIva' => (float) ($fac['iva_10'] ?? 0) + (float) ($fac['iva_5'] ?? 0),
            'liquidacionIva5' => (float) ($fac['iva_5'] ?? 0),
            'liquidacionIva10' => (float) ($fac['iva_10'] ?? 0),
            'totalLiquidacion' => (float) ($fac['iva_10'] ?? 0) + (float) ($fac['iva_5'] ?? 0),
            'descuento' => 0,
            'totalFactura' => $total,
        ],
        // Extras propios (la plantilla los ignora; sirven al KuDE/API)
        'cdc' => $fac['cdc'] ?? null,
        'qrTexto' => $fac['qr_texto'] ?? null,
    ];
}

function deliveryFacturaFile(int $idFactura, string $mode): string
{
    $baseDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'factura-py';
    $scriptPath = $baseDir . DIRECTORY_SEPARATOR . 'generate-factura.js';
    if (!is_file($scriptPath)) {
        sendJson(['message' => 'Motor de facturas no instalado (falta factura-py)'], 501);
    }
    $model = buildDeliveryFacturaModel($idFactura);
    $payload = json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        sendJson(['message' => 'No se pudo serializar la factura'], 500);
    }
    // El payload viaja en archivo (en Windows la línea de comandos lo partiría).
    $inputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factura_in_' . uniqid('', true) . '.json';
    @file_put_contents($inputPath, $payload);
    $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factura_' . uniqid('', true) . ($mode === 'pdf' ? '.pdf' : '.html');
    $command = sprintf(
        'node %s --mode %s --input %s --output %s',
        escapeshellarg($scriptPath),
        escapeshellarg($mode),
        escapeshellarg($inputPath),
        escapeshellarg($outputPath)
    );
    exec($command . ' 2>&1', $lines, $exitCode);
    @unlink($inputPath);
    if ($exitCode !== 0 || !is_file($outputPath)) {
        $err = implode("\n", $lines);
        if (stripos($err, 'Could not find Chrome') !== false || stripos($err, 'puppeteer') !== false) {
            sendJson(['message' => 'PDF no disponible en este servidor (falta Chromium). Usa KuDE/HTML.'], 501);
        }
        sendJson(['message' => 'Error generando comprobante: ' . mb_substr(trim($err), 0, 300)], 500);
    }
    return $outputPath;
}

function facturaHtmlController(object $claims, int $id): never
{
    $tmp = deliveryFacturaFile($id, 'html');
    $html = @file_get_contents($tmp);
    @unlink($tmp);
    if ($html === false) {
        sendJson(['message' => 'No se pudo recuperar el HTML'], 500);
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

function facturaPdfController(object $claims, int $id): never
{
    $tmp = deliveryFacturaFile($id, 'pdf');
    if (!is_file($tmp)) {
        sendJson(['message' => 'No se pudo generar el PDF'], 500);
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="factura-' . $id . '.pdf"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}
