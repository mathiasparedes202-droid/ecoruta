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
{
    $fac = facturaConItems($id);
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
