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
    if ($orderId <= 0) {
        sendJson(['message' => 'ID de pedido inválido'], 422);
    }
    $body = requestBody();
    // Validación de los campos del receptor (si vienen, deben ser coherentes).
    $ruc = trim((string) ($body['ruc_receptor'] ?? ''));
    if ($ruc !== '' && !preg_match('/^\d{4,15}(-\d{1,2})?$/', $ruc)) {
        sendJson(['message' => 'RUC del receptor inválido (ej: 80012345-6)'], 422);
    }
    $rs = trim((string) ($body['razon_social_receptor'] ?? ''));
    if ($rs !== '' && mb_strlen($rs) < 3) {
        sendJson(['message' => 'Razón social del receptor: mín. 3 caracteres'], 422);
    }
    $cond = (string) ($body['condicion_venta'] ?? 'Contado');
    if (!in_array($cond, ['Contado', 'Crédito'], true)) {
        sendJson(['message' => 'Condición de venta inválida (solo Contado o Crédito)'], 422);
    }
    $receptor = [
        'ruc' => $ruc,
        'razon_social' => $rs,
        'direccion' => trim((string) ($body['direccion_receptor'] ?? '')),
        'condicion_venta' => $cond,
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
    // QR en vivo: siempre apunta al link que reabre esta factura (no depende
    // del texto guardado, que en facturas antiguas era solo un código seco).
    $qrLink = facturaPublicUrl($id);
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($qrLink);
    // Normaliza ítems antiguos (guardaban el total con IVA en el ítem).
    $suma10 = 0.0;
    foreach (($fac['items'] ?? []) as $it) {
        if ((int) ($it['iva_tipo'] ?? 10) === 10) {
            $suma10 += (float) ($it['subtotal'] ?? 0);
        }
    }
    $gravada10 = (float) ($fac['gravada_10'] ?? 0);
    $factor = ($suma10 > 0 && $gravada10 > 0 && abs($suma10 - $gravada10) > 0.01) ? ($gravada10 / $suma10) : 1.0;
    $rows = '';
    foreach (($fac['items'] ?? []) as $it) {
        $rows .= '<tr><td>' . htmlspecialchars((string) $it['concepto']) . '</td><td style="text-align:right">'
            . htmlspecialchars((string) $it['cantidad']) . '</td><td style="text-align:right">₲ '
            . number_format((float) $it['precio_unitario'] * $factor, 0, ',', '.') . '</td><td style="text-align:right">₲ '
            . number_format((float) $it['subtotal'] * $factor, 0, ',', '.') . '</td></tr>';
    }
    $f = fn($v) => '₲ ' . number_format((float) $v, 0, ',', '.');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>KuDE ' . htmlspecialchars((string) $fac['numero_completo'])
        . '</title><style>body{font-family:Arial,sans-serif;max-width:760px;margin:24px auto;color:#111}table{width:100%;border-collapse:collapse}td,th{border:1px solid #999;padding:6px;font-size:13px}.head{display:flex;justify-content:space-between;gap:16px}.badge{display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:bold;background:' . ($fac['estado'] === 'Vigente' ? '#dcfce7' : '#fee2e2') . '}.muted{color:#555;font-size:12px}@media print{.noprint{display:none}}</style></head><body>'
        . '<div class="head"><div><h2>EcoRuta S.A. — KuDE</h2><p class="muted">Documento pre-fiscal generado por el sistema (fase SIFEN en homologación).</p></div>'
        . '<div style="text-align:center"><img src="' . $qrUrl . '" alt="QR" width="180" height="180"><br><small>' . htmlspecialchars($qrLink) . '</small></div></div>'
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

// Logo del emisor como data URI (el motor no tiene acceso a archivos del servidor
// y en Render solo existe backend/). Si falta, la plantilla muestra el recuadro.
function facturaLogoDataUrl(): ?string
{
    $candidates = [
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'EcoRutaLogo.png',
        dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'EcoRutaLogo.png',
    ];
    foreach ($candidates as $file) {
        if (is_file($file) && filesize($file) > 100 && filesize($file) < 3 * 1024 * 1024) {
            $bin = @file_get_contents($file);
            if ($bin !== false) {
                return 'data:image/png;base64,' . base64_encode($bin);
            }
        }
    }
    return null;
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
    // Normaliza items: los emitidos antes del fix guardaban el TOTAL (con IVA)
    // en el ítem; se re-escalan a la gravada real para que las columnas cuadren.
    $suma10 = 0.0;
    foreach (($fac['items'] ?? []) as $it) {
        if ((int) ($it['iva_tipo'] ?? 10) === 10) {
            $suma10 += (float) ($it['subtotal'] ?? 0);
        }
    }
    $gravada10 = (float) ($fac['gravada_10'] ?? 0);
    $factor = ($suma10 > 0 && $gravada10 > 0 && abs($suma10 - $gravada10) > 0.01) ? ($gravada10 / $suma10) : 1.0;
    $detalles = array_map(function ($it) use ($factor) {
        $sub = (float) ($it['subtotal'] ?? 0) * $factor;
        $iva = (int) ($it['iva_tipo'] ?? 10);
        return [
            'idDetalleVenta' => $it['id_item'] ?? null,
            'idProducto' => null,
            'cantidad' => (float) ($it['cantidad'] ?? 1),
            'unidadMedida' => 'Servicio',
            'descripcion' => $it['concepto'] ?? 'Servicio de reparto',
            'precioUnitario' => (float) ($it['precio_unitario'] ?? 0) * $factor,
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
            'logoDataUrl' => facturaLogoDataUrl(),
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
        // QR = link público que reabre la factura al escanear (genera en vivo
        // para que también funcione en facturas emitidas antes del fix).
        'qrTexto' => facturaPublicUrl((int) $fac['id_factura']),
    ];
}

function locateFacturaPyScript(): ?string
{
    $cands = [];
    $env = getenv('FACTURA_PY_PATH');
    if (is_string($env) && $env !== '') {
        $env = rtrim($env, "/\\");
        $cands[] = $env . DIRECTORY_SEPARATOR . 'generate-factura.js';
        $cands[] = $env; // por si apunta directo al .js
    }
    $cands[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'factura-py' . DIRECTORY_SEPARATOR . 'generate-factura.js';
    $cands[] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'factura-py' . DIRECTORY_SEPARATOR . 'generate-factura.js';
    $cands[] = '/var/www/factura-py/generate-factura.js';
    $cands[] = '/var/www/html/factura-py/generate-factura.js';
    foreach ($cands as $c) {
        if (is_string($c) && $c !== '' && is_file($c)) {
            return $c;
        }
    }
    return null;
}

function facturaNodeBin(): string
{
    $env = getenv('FACTURA_NODE_BIN');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    return 'node';
}

function facturaEsc($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function facturaGs($v): string
{
    return number_format((float) ($v ?? 0), 0, ',', '.');
}

function facturaNumLetras(int $n): string
{
    if ($n === 0) return 'CERO';
    if ($n < 0 || $n > 999999999) return (string) $n;
    $U = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    $D = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $C = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
    $t = function ($x) use (&$t, $U, $D, $C) {
        if ($x < 30) return $U[$x];
        if ($x < 100) { $d = intdiv($x, 10); $r = $x % 10; return $r === 0 ? $D[$d] : $D[$d] . ' Y ' . $U[$r]; }
        if ($x === 100) return 'CIEN';
        $c = intdiv($x, 100); $r = $x % 100;
        return $r === 0 ? $C[$c] : $C[$c] . ' ' . $t($r);
    };
    $m = function ($x) use (&$m, &$t) {
        if ($x < 1000) return $t($x);
        $miles = intdiv($x, 1000); $r = $x % 1000;
        $head = $miles === 1 ? 'MIL' : $t($miles) . ' MIL';
        return $r === 0 ? $head : $head . ' ' . $t($r);
    };
    if ($n < 1000000) return $m($n);
    $mill = intdiv($n, 1000000); $r = $n % 1000000;
    $head = $mill === 1 ? 'UN MILLÓN' : $m($mill) . ' MILLONES';
    return $r === 0 ? $head : $head . ' ' . $m($r);
}

function facturaMontoLetras($total): string
{
    $v = (float) ($total ?? 0);
    $entero = (int) floor($v);
    $dec = (int) round(($v - $entero) * 100);
    return facturaNumLetras($entero) . ' GUARANÍES ' . str_pad((string) $dec, 2, '0', STR_PAD_LEFT) . '/100';
}

// Fallback 100% PHP (sin Node): replica la plantilla factura.template.js
// para que el HTML/KuDE nunca dependa de factura-py ni de Chromium.
function facturaHtmlFallback(array $model): string
{
    $emisor = $model['emisor'] ?? [];
    $fac = $model['factura'] ?? [];
    $cli = $model['cliente'] ?? [];
    $det = $model['detalles'] ?? [];
    $res = $model['resumen'] ?? [];
    $esContado = ($fac['condicionVenta'] ?? 'Contado') === 'Contado';
    $esCredito = ($fac['condicionVenta'] ?? '') === 'Crédito';
    $rows = '';
    if (!is_array($det) || count($det) === 0) {
        $rows = '<tr><td colspan="7" style="text-align:center;height:180px">No existen productos registrados.</td></tr>';
    } else {
        foreach ($det as $d) {
            $rows .= '<tr><td style="text-align:center">' . facturaEsc($d['cantidad'] ?? '') . '</td>'
                . '<td style="text-align:center">' . facturaEsc($d['unidadMedida'] ?? 'Servicio') . '</td>'
                . '<td style="text-transform:uppercase">' . facturaEsc($d['descripcion'] ?? '') . '</td>'
                . '<td style="text-align:right;white-space:nowrap">' . facturaGs($d['precioUnitario'] ?? 0) . '</td>'
                . '<td style="text-align:right;white-space:nowrap">' . (((float) ($d['exenta'] ?? 0)) > 0 ? facturaGs($d['exenta']) : '') . '</td>'
                . '<td style="text-align:right;white-space:nowrap">' . (((float) ($d['iva5'] ?? 0)) > 0 ? facturaGs($d['iva5']) : '') . '</td>'
                . '<td style="text-align:right;white-space:nowrap">' . (((float) ($d['iva10'] ?? 0)) > 0 ? facturaGs($d['iva10']) : '') . '</td></tr>';
        }
    }
    $f = fn($v) => 'Gs. ' . facturaGs($v);
    // Cuadre de montos: SUBTOTAL (gravado) + IVA - descuento = TOTAL A PAGAR.
    $subtotalGravado = (float) ($res['subtotalExenta'] ?? 0) + (float) ($res['subtotalIva5'] ?? 0) + (float) ($res['subtotalIva10'] ?? 0);
    $descuento = (float) ($res['descuento'] ?? 0);
    $totalIva = (float) ($res['totalLiquidacion'] ?? 0);
    $totalPagar = round($subtotalGravado - $descuento + $totalIva, 0);
    $qrTexto = (string) ($model['qrTexto'] ?? '');
    $qrImg = $qrTexto !== ''
        ? '<img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qrTexto) . '" alt="QR factura" width="150" height="150" style="display:block;margin:6px auto">'
        : '';
    $estado = (string) ($fac['estado'] ?? '');
    $anulada = $estado === 'Anulada' ? '<div style="margin-bottom:8px;padding:5px;border:2px solid #000;text-align:center;font-size:15px;font-weight:700">FACTURA ANULADA</div>' : '';
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Factura ' . facturaEsc($fac['numeroFactura'] ?? '') . '</title>'
        . '<style>*{box-sizing:border-box}body{margin:0;padding:0;color:#000;background:#fff;font-family:Arial,Helvetica,sans-serif;font-size:10px}'
        . '.factura{width:100%;min-height:280mm;border:1.5px solid #000;padding:8px}.cinta{background:#000;color:#fff;text-align:center;font-size:13px;font-weight:700;letter-spacing:2px;padding:5px;margin-bottom:8px}'
        . '.enc{display:grid;grid-template-columns:1.4fr 1fr;border-bottom:1px solid #000}.em{padding:8px;border-right:1px solid #000}.df{padding:8px}'
        . '.nf{margin:8px 0;padding:5px;border:1px solid #000;text-align:center;font-size:16px;font-weight:700}.tim{margin:3px 0;text-align:center;font-weight:700}.vig{margin:3px 0;text-align:center}'
        . '.fila{display:grid;grid-template-columns:1fr 1fr;min-height:29px;border-bottom:1px solid #000}.campo{padding:6px}.campo:first-child{border-right:1px solid #000}.et{font-weight:700;text-transform:uppercase}'
        . 'table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #000}thead th{padding:5px 3px;text-align:center;font-size:9px}tbody td{height:25px;padding:4px;vertical-align:top}'
        . '.tot{display:grid;grid-template-columns:1fr 1fr;border:1px solid #000;border-top:0}.tot div{padding:8px;font-weight:700}.tot div:last-child{text-align:right}'
        . '.letras{border:1px solid #000;border-top:0;padding:6px 8px;font-size:9px;font-weight:700;text-transform:uppercase}'
        . '.liq{display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;border:1px solid #000;border-top:0}.liq>div{min-height:31px;padding:7px 4px;border-right:1px solid #000;text-align:center}.liq>div:last-child{border-right:0}'
        . '.verif{margin-top:10px;border:1px solid #000;padding:6px 8px;font-size:8px;line-height:1.5;word-break:break-all}</style></head><body><main class="factura">'
        . '<div class="cinta">FACTURA ELECTRÓNICA · KuDE — REPRESENTACIÓN IMPRESA</div>' . $anulada
        . '<header class="enc"><section class="em"><h1 style="margin:0 0 5px;font-size:17px;text-transform:uppercase">' . facturaEsc($emisor['razonSocial'] ?? '') . '</h1>'
        . '<p><strong>RUC:</strong> ' . facturaEsc($emisor['ruc'] ?? '') . '</p><p>' . facturaEsc($emisor['direccion'] ?? '') . '</p>'
        . '<p>' . facturaEsc($emisor['telefono'] ?? '') . '</p><p>' . facturaEsc($emisor['email'] ?? '') . '</p></section>'
        . '<section class="df"><h2 style="margin:0;text-align:center;font-size:21px">' . facturaEsc($fac['tipoComprobante'] ?? 'FACTURA') . '</h2>'
        . '<div class="nf">' . facturaEsc($fac['numeroFactura'] ?? '') . '</div>'
        . '<p class="tim">TIMBRADO N.º ' . facturaEsc($fac['timbrado'] ?? '') . '</p>'
        . '<p class="vig">Válido hasta: ' . facturaEsc($emisor['vigenciaTimbrado'] ?? '') . '</p>'
        . '<p class="tim">CDC CONTROL ' . facturaEsc(substr((string) ($model['cdc'] ?? ''), 0, 16)) . '</p></section></header>'
        . '<section style="border-bottom:1px solid #000">'
        . '<div class="fila"><div class="campo"><span class="et">Fecha de emisión:</span> ' . facturaEsc($fac['fechaEmision'] ?? '') . '</div>'
        . '<div class="campo"><span class="et">Condición de venta:</span> CONTADO [' . ($esContado ? 'X' : ' ') . '] CRÉDITO [' . ($esCredito ? 'X' : ' ') . ']</div></div>'
        . '<div class="fila"><div class="campo"><span class="et">Nombre o razón social:</span> ' . facturaEsc($cli['razonSocial'] ?? '') . '</div>'
        . '<div class="campo"><span class="et">RUC / C.I.:</span> ' . facturaEsc($cli['rucCi'] ?? '') . '</div></div>'
        . '<div class="fila"><div class="campo"><span class="et">Teléfono:</span> ' . facturaEsc($cli['celular'] ?? '') . '</div>'
        . '<div class="campo"><span class="et">Plazo:</span> ' . facturaEsc($fac['plazo'] ?? '') . '</div></div>'
        . '<div class="fila"><div class="campo"><span class="et">Dirección:</span> ' . facturaEsc($cli['direccion'] ?? '') . '</div>'
        . '<div class="campo"><span class="et">Método de pago:</span> ' . facturaEsc($fac['metodoPago'] ?? '') . '</div></div>'
        . '</section>'
        . '<table><thead><tr><th rowspan="2">CANTIDAD</th><th rowspan="2">UNIDAD</th><th rowspan="2">DESCRIPCIÓN</th><th rowspan="2">PRECIO UNITARIO</th><th colspan="3">VALOR DE VENTA</th></tr>'
        . '<tr><th>EXENTAS</th><th>5 %</th><th>10 %</th></tr></thead><tbody>' . $rows
        . '<tr><td colspan="4" style="text-align:right;font-weight:700">SUBTOTALES</td>'
        . '<td style="text-align:right">' . facturaGs($res['subtotalExenta'] ?? 0) . '</td>'
        . '<td style="text-align:right">' . facturaGs($res['subtotalIva5'] ?? 0) . '</td>'
        . '<td style="text-align:right">' . facturaGs($res['subtotalIva10'] ?? 0) . '</td></tr>'
        . '</tbody></table>'
        . '<section class="tot"><div>SUBTOTAL</div><div>' . $f($subtotalGravado) . '</div></section>'
        . '<section class="tot"><div>DESCUENTO</div><div>' . $f($descuento) . '</div></section>'
        . '<section class="tot"><div>IVA 10 %</div><div>' . $f($totalIva) . '</div></section>'
        . '<section class="tot"><div>TOTAL A PAGAR</div><div>' . $f($totalPagar) . '</div></section>'
        . '<div class="letras">SON: ' . facturaEsc(facturaMontoLetras($totalPagar)) . '</div>'
        . '<section class="liq"><div><strong>LIQUIDACIÓN DEL IVA</strong></div>'
        . '<div>IVA 5 %<br>' . facturaGs($res['liquidacionIva5'] ?? 0) . '</div>'
        . '<div>IVA 10 %<br>' . facturaGs($res['liquidacionIva10'] ?? 0) . '</div>'
        . '<div>TOTAL IVA<br>' . facturaGs($res['totalLiquidacion'] ?? 0) . '</div></section>'
        . '<div class="verif">' . $qrImg
        . '<strong>Sello digital (control interno):</strong> ' . facturaEsc($model['cdc'] ?? '—') . '<br>'
        . '<strong>Verifique su factura (escanee el QR o abra el enlace):</strong> ' . facturaEsc($model['qrTexto'] ?? '—') . '<br>'
        . '<strong>Timbrado vigente hasta:</strong> ' . facturaEsc($emisor['vigenciaTimbrado'] ?? '—')
        . ' — Esta es una representación impresa del documento tributario electrónico.</div>'
        . '</main></body></html>';
}

function deliveryFacturaWriteFallbackHtml(array $model): string
{
    $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factura_' . uniqid('', true) . '.html';
    @file_put_contents($outputPath, facturaHtmlFallback($model));
    return $outputPath;
}

function deliveryFacturaFile(int $idFactura, string $mode): string
{
    $model = buildDeliveryFacturaModel($idFactura);
    $payload = json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        sendJson(['message' => 'No se pudo serializar la factura'], 500);
    }
    $scriptPath = locateFacturaPyScript();
    // Sin motor: el HTML se resuelve con fallback PHP; el PDF sí requiere Node/Chromium.
    if ($scriptPath === null) {
        if ($mode === 'html') {
            return deliveryFacturaWriteFallbackHtml($model);
        }
        sendJson(['message' => 'Motor de facturas no disponible en este servidor (falta factura-py o Node). Usa KuDE/HTML.'], 501);
    }
    // El payload viaja en archivo (en Windows la línea de comandos lo partiría).
    $inputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factura_in_' . uniqid('', true) . '.json';
    @file_put_contents($inputPath, $payload);
    $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factura_' . uniqid('', true) . ($mode === 'pdf' ? '.pdf' : '.html');
    $command = sprintf(
        '%s %s --mode %s --input %s --output %s',
        facturaNodeBin(),
        escapeshellarg($scriptPath),
        escapeshellarg($mode),
        escapeshellarg($inputPath),
        escapeshellarg($outputPath)
    );
    exec($command . ' 2>&1', $lines, $exitCode);
    @unlink($inputPath);
    if ($exitCode !== 0 || !is_file($outputPath)) {
        $err = implode("\n", $lines);
        if ($mode === 'html') {
            // Node ausente o plantilla rota: no romper la visualización.
            if (is_file($outputPath)) @unlink($outputPath);
            return deliveryFacturaWriteFallbackHtml($model);
        }
        if (stripos($err, 'Could not find Chrome') !== false || stripos($err, 'puppeteer') !== false || stripos($err, 'chrome') !== false) {
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
