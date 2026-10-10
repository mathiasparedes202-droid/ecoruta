<?php

namespace App\Controllers;

use App\Services\VentaService;
use Config\Database;
use Core\Controller;
use Core\Request;
use Core\Response;
use PDO;

/**
 * SyncController — lado SERVIDOR (instalar el mismo archivo en LOCAL y en WEB).
 *
 * Endpoints (ver routes/api.php):
 *  GET  /api/sync/health          -> público, lo usa el agente para detectar caída
 *  GET  /api/sync/pull?since=... -> incremental web->local / local->web
 *  POST /api/sync/push            -> lote idempotente local->web (anti-duplicado + anti-negativo)
 *
 * Garantías delivery (pedidos):
 *  - Idempotencia por pedidos.sync_uuid: reintentos del agente NO duplican
 *    pedidos ni cobran dos veces.
 *  - Regla de dinero: jamás revierte pagado=1 a 0.
 *
 * Garantías tienda (ventas, cuando la base las tiene):
 *  - Idempotencia por venta.sync_uuid: reintentos del agente NO duplican facturas.
 *  - Folio único: si numero_factura choca con otra venta (distinto uuid),
 *    se reasigna el siguiente folio libre y se devuelve para corregir el local.
 *  - Stock nunca negativo: validación con SELECT ... FOR UPDATE dentro de
 *    transacción + triggers DB (migración 44). Si no alcanza, la venta queda
 *    como CONFLICTO y no descuenta.
 */
class SyncController extends Controller
{
    private PDO $db;
    private VentaService $ventas;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        // El repo mezcla dos módulos con distinta base: delivery (ecoruta_db) y
        // ventas/stock (floracia_db). El sync opera sobre la de ventas sin
        // cambiar DB_NAME global: solo en este request se redirige la conexión.
        $syncDb = trim((string)($_ENV['SYNC_DB_NAME'] ?? getenv('SYNC_DB_NAME') ?: ''));
        if ($syncDb !== '') {
            $_ENV['DB_NAME'] = $syncDb;
            putenv('DB_NAME=' . $syncDb);
        }
        $this->db = (new Database())->connect();
        $this->ventas = new VentaService();
    }

    // GET /api/sync/health (público)
    public function health(): void
    {
        try {
            $this->db->query('SELECT 1')->fetch();
            $this->response->json([
                'success' => true,
                'server_time' => date('Y-m-d H:i:s'),
                'service' => 'ecoruta-api',
            ], 200);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => 'DB no disponible'], 503);
        }
    }

    // GET /api/sync/pull?since=YYYY-MM-DD HH:MM:SS&limit=200
    // Entidades según la base: pedidos (delivery, ecoruta_db) y/o
    // ventas (tienda, floracia_db). Devuelve las que existan.
    public function pull(): void
    {
        try {
            $hasPedidos = $this->tableExists('pedidos');
            $hasVentas = $this->tableExists('venta');
            if (!$hasPedidos && !$hasVentas) {
                $this->response->json(['success' => false, 'message' => 'Esta base no tiene pedidos ni ventas para sincronizar'], 501);
                return;
            }
            $since = (string)($this->request->getQueryParam('since') ?? '2000-01-01 00:00:00');
            $limit = max(1, min(500, (int)($this->request->getQueryParam('limit') ?? 200)));

            // Validar formato fecha para evitar inyección (aunque usamos prepare)
            $ts = strtotime($since);
            if ($ts === false) {
                $since = '2000-01-01 00:00:00';
            } else {
                $since = date('Y-m-d H:i:s', $ts);
            }

            $ventas = [];
            if ($hasVentas) {
                $hasUuid = $this->colExists('venta', 'sync_uuid');
                $hasOrigen = $this->colExists('venta', 'origen');
                $hasUpdated = $this->colExists('venta', 'updated_at');

                $updatedExpr = $hasUpdated ? 'v.updated_at' : 'v.fecha_emision';
                $uuidSel = $hasUuid ? 'v.sync_uuid' : 'NULL AS sync_uuid';
                $origenSel = $hasOrigen ? 'v.origen' : "'web' AS origen";

                $q = "SELECT v.*, {$uuidSel}, {$origenSel}, {$updatedExpr} AS _sync_ts
                      FROM venta v
                      WHERE {$updatedExpr} > :since
                      ORDER BY {$updatedExpr} ASC
                      LIMIT {$limit}";
                $st = $this->db->prepare($q);
                $st->execute(['since' => $since]);
                $ventas = $st->fetchAll(PDO::FETCH_ASSOC);
            }

            // Detalles de esas ventas
            $ids = array_column($ventas, 'id_venta');
            $detalles = [];
            if ($ids && $this->tableExists('detalle_venta')) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $dst = $this->db->prepare("SELECT * FROM detalle_venta WHERE id_venta IN ($ph)");
                $dst->execute($ids);
                $detalles = $dst->fetchAll(PDO::FETCH_ASSOC);
            }

            // Niveles de stock actuales (el agente los usa para corregir su caché local)
            $stocks = [];
            if ($this->tableExists('stock_producto')) {
                $stocks = $this->db->query(
                    'SELECT sp.id_producto, sp.cantidad, sp.fecha_actualizacion FROM stock_producto sp'
                )->fetchAll(PDO::FETCH_ASSOC);
            }
            $insumos = [];
            try {
                if ($this->tableExists('insumo')) {
                    $insumos = $this->db->query(
                        'SELECT id_insumo, stock FROM insumo'
                    )->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (\Throwable $e) {
                $insumos = [];
            }

            // Pedidos delivery (ecoruta_db): incremental por updated_at/fecha_solicitud
            $pedidos = [];
            $historial = [];
            $maxTs = $since;
            if ($hasPedidos) {
                $pedTsExpr = $this->colExists('pedidos', 'updated_at') ? 'p.updated_at' : 'p.fecha_solicitud';
                $pedUuidSel = $this->colExists('pedidos', 'sync_uuid') ? 'p.sync_uuid' : 'NULL AS sync_uuid';
                $pedOrigenSel = $this->colExists('pedidos', 'origen') ? 'p.origen' : "'web' AS origen";
                $qp = "SELECT p.*, {$pedUuidSel}, {$pedOrigenSel}, {$pedTsExpr} AS _sync_ts
                       FROM pedidos p
                       WHERE {$pedTsExpr} > :since
                       ORDER BY {$pedTsExpr} ASC
                       LIMIT {$limit}";
                $stp = $this->db->prepare($qp);
                $stp->execute(['since' => $since]);
                $pedidos = $stp->fetchAll(PDO::FETCH_ASSOC);

                $pids = array_column($pedidos, 'id_pedido');
                if ($pids && $this->tableExists('historial_estados')) {
                    $ph = implode(',', array_fill(0, count($pids), '?'));
                    $sth = $this->db->prepare("SELECT * FROM historial_estados WHERE id_pedido IN ($ph) ORDER BY fecha_cambio ASC");
                    $sth->execute($pids);
                    $historial = $sth->fetchAll(PDO::FETCH_ASSOC);
                }
                foreach ($pedidos as $p) {
                    if (!empty($p['_sync_ts']) && $p['_sync_ts'] > $maxTs) {
                        $maxTs = $p['_sync_ts'];
                    }
                }
            }

            foreach ($ventas as $v) {
                if (!empty($v['_sync_ts']) && $v['_sync_ts'] > $maxTs) {
                    $maxTs = $v['_sync_ts'];
                }
            }
            foreach ($pedidos as $p) {
                if (!empty($p['_sync_ts']) && $p['_sync_ts'] > $maxTs) {
                    $maxTs = $p['_sync_ts'];
                }
            }

            $this->response->json([
                'success' => true,
                'data' => [
                    'ventas' => $ventas,
                    'detalles' => $detalles,
                    'stock_producto' => $stocks,
                    'insumos' => $insumos,
                    'pedidos' => $pedidos,
                    'historial' => $historial,
                ],
                'server_time' => date('Y-m-d H:i:s'),
                'max_ts' => $maxTs,
            ], 200);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sync/push
     * Body: { "pedidos": [ {pedido completo + sync_uuid}, ... ],
     *         "ventas": [ {venta completa + detalles + sync_uuid}, ... ] }
     *
     * Respuesta por item:
     *  { sync_uuid, status: applied|duplicado|conflicto_pago|conflicto_folio_reasignado|conflicto_stock|error,
     *    id_pedido|id_venta, message }
     *
     * Regla de dinero (delivery): un pedido que ya figura pagado=1 en el
     * servidor jamás se revierte a 0 por un reintento; el reintento con el
     * mismo sync_uuid responde `duplicado` sin tocar nada.
     */
    public function push(): void
    {
        try {
            $hasPedidos = $this->tableExists('pedidos');
            $hasVentas = $this->tableExists('venta');
            if (!$hasPedidos && !$hasVentas) {
                $this->response->json(['success' => false, 'message' => 'Esta base no tiene pedidos ni ventas para sincronizar'], 501);
                return;
            }
            $body = $this->request->getBody();
            $pedidos = $body['pedidos'] ?? [];
            $ventas = $body['ventas'] ?? $body['items'] ?? [];
            if (!is_array($pedidos) || !is_array($ventas)) {
                $this->response->json(['success' => false, 'message' => 'pedidos y ventas deben ser arreglos'], 422);
                return;
            }
            // Compat: antes solo se mandaba {ventas} o {items:ventas}; si hay
            // tabla pedidos y el lote trae items sin formato venta, se ignora aquí.
            if (count($pedidos) + count($ventas) > 100) {
                $this->response->json(['success' => false, 'message' => 'Máximo 100 registros por lote'], 422);
                return;
            }
            if (!$pedidos && !$ventas) {
                $this->response->json(['success' => false, 'message' => 'Lote vacío: envía pedidos y/o ventas'], 422);
                return;
            }

            $user = $this->request->getUser();
            $usuarioId = 0;
            if ($user) {
                $usuarioId = (int)(is_object($user)
                    ? ($user->id_usuario ?? 0)
                    : ($user['id_usuario'] ?? 0));
            }
            if ($usuarioId <= 0) {
                // Usuario sistema para ventas sincronizadas (debe existir id 1 o se usa NULL)
                $usuarioId = $this->resolveSystemUserId();
            }

            $results = [];
            foreach ($pedidos as $p) {
                $results[] = $this->applyOnePedido((array)$p, $usuarioId);
            }
            foreach ($ventas as $v) {
                $results[] = $this->applyOneVenta((array)$v, $usuarioId);
            }

            $this->response->json([
                'success' => true,
                'server_time' => date('Y-m-d H:i:s'),
                'results' => $results,
            ], 200);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function applyOneVenta(array $v, int $usuarioId): array
    {
        $uuid = trim((string)($v['sync_uuid'] ?? ''));
        if ($uuid === '' || strlen($uuid) < 8) {
            return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'sync_uuid requerido'];
        }

        try {
            // 1) Idempotencia: ¿ya existe este uuid?
            if ($this->colExists('venta', 'sync_uuid')) {
                $st = $this->db->prepare('SELECT id_venta, numero_factura FROM venta WHERE sync_uuid = :u LIMIT 1');
                $st->execute(['u' => $uuid]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return [
                        'sync_uuid' => $uuid, 'status' => 'duplicado',
                        'id_venta' => (int)$row['id_venta'],
                        'numero_factura' => $row['numero_factura'],
                        'message' => 'Ya existía (reintento seguro, no se duplicó)',
                    ];
                }
            }

            // 2) Normalizar payload del agente al formato de registrarVenta()
            $payload = $this->normalizeVentaPayload($v);

            // 3) Conflicto de folio: mismo número pero distinto uuid -> reasignar
            if (!empty($payload['numero_factura'])) {
                $st = $this->db->prepare('SELECT id_venta FROM venta WHERE numero_factura = :n LIMIT 1');
                $st->execute(['n' => $payload['numero_factura']]);
                if ($st->fetch(PDO::FETCH_ASSOC)) {
                    $nuevo = $this->nextFreeFolio();
                    $payload['numero_factura'] = $nuevo;
                    $folioReasignado = true;
                }
            }
            $folioReasignado = $folioReasignado ?? false;

            // 4) Registrar con las validaciones normales (incluye stock, nunca negativo)
            //    registrarVenta() lanza excepción si no hay stock -> lo convertimos en conflicto.
            $payload['sync_uuid'] = $uuid;
            $payload['origen'] = 'local';
            $idVenta = $this->ventas->registrarVenta($payload, $usuarioId);

            // 5) Asegurar columnas sync (por si el repository aún no las persiste)
            $this->stampSyncColumns($idVenta, $uuid);

            $venta = $this->ventas->obtenerVentaConDetalles($idVenta);
            $folioFinal = $venta['venta']->numero_factura ?? $payload['numero_factura'];

            return [
                'sync_uuid' => $uuid,
                'status' => $folioReasignado ? 'conflicto_folio_reasignado' : 'applied',
                'id_venta' => $idVenta,
                'numero_factura' => $folioFinal,
                'message' => $folioReasignado
                    ? "Folio en uso en web, se reasignó a $folioFinal (actualiza tu local)"
                    : 'Aplicada en web',
            ];
        } catch (\InvalidArgumentException $e) {
            return $this->conflictOrError($uuid, $e);
        } catch (\Throwable $e) {
            return $this->conflictOrError($uuid, $e);
        }
    }

    private function conflictOrError(string $uuid, \Throwable $e): array
    {
        $msg = $e->getMessage();
        // Todo lo que huela a stock -> conflicto_stock (revisión manual, NO reintentar a ciegas)
        if (stripos($msg, 'stock') !== false || stripos($msg, 'STOCK_NEGATIVO') !== false) {
            return ['sync_uuid' => $uuid, 'status' => 'conflicto_stock', 'message' => $msg];
        }
        if (stripos($msg, 'factura') !== false) {
            return ['sync_uuid' => $uuid, 'status' => 'conflicto_folio', 'message' => $msg];
        }
        return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => $msg];
    }

    private function normalizeVentaPayload(array $v): array
    {
        // El agente manda lo que guardó del local; aceptamos ambos estilos de keys.
        $detalles = $v['detalles'] ?? $v['items'] ?? [];
        return [
            'id_cliente' => $v['id_cliente'] ?? null,
            'id_pedido' => $v['id_pedido'] ?? null,
            'numero_factura' => $v['numero_factura'] ?? null,
            'timbrado' => $v['timbrado'] ?? '',
            'fecha_emision' => $v['fecha_emision'] ?? date('Y-m-d H:i:s'),
            'estado_factura' => 'Vigente',
            'tipo_comprobante' => $v['tipo_comprobante'] ?? 'Factura',
            'numero_comprobante' => $v['numero_comprobante'] ?? null,
            'delivery_option' => $v['delivery_option'] ?? 'Retiro',
            'direccion_entrega' => $v['direccion_entrega'] ?? null,
            'observaciones' => ($v['observaciones'] ?? '') . ' [sync local]',
            'condicion_venta' => $v['condicion_venta'] ?? 'Contado',
            'plazo' => $v['plazo'] ?? null,
            'detalles' => array_map(function ($d) {
                $d = (array)$d;
                return [
                    'id_producto' => $d['id_producto'] ?? null,
                    'id_insumo' => $d['id_insumo'] ?? null,
                    'cantidad' => $d['cantidad'] ?? 0,
                    'precio_unitario' => $d['precio_unitario'] ?? 0,
                    'iva_tipo' => $d['iva_tipo'] ?? 10,
                ];
            }, is_array($detalles) ? $detalles : []),
        ];
    }

    private function nextFreeFolio(): string
    {
        // Parte del último folio y avanza hasta uno libre (máx 1000 intentos)
        $st = $this->db->query("SELECT numero_factura FROM venta WHERE numero_factura REGEXP '^[0-9]{3}-[0-9]{3}-[0-9]{7}$' ORDER BY id_venta DESC LIMIT 1");
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
        $base = $row['numero_factura'] ?? '001-001-0000001';
        if (!preg_match('/^(\d{3})-(\d{3})-(\d{7})$/', $base, $m)) {
            $base = '001-001-0000001';
            preg_match('/^(\d{3})-(\d{3})-(\d{7})$/', $base, $m);
        }
        for ($i = 0; $i < 1000; $i++) {
            $seq = (int)$m[3] + $i + 1;
            $cand = sprintf('%03d-%03d-%07d', $m[1], $m[2], $seq);
            $chk = $this->db->prepare('SELECT 1 FROM venta WHERE numero_factura = :n LIMIT 1');
            $chk->execute(['n' => $cand]);
            if (!$chk->fetchColumn()) {
                return $cand;
            }
        }
        throw new \RuntimeException('No se pudo asignar un folio libre');
    }

    private function stampSyncColumns(int $idVenta, string $uuid): void
    {
        try {
            $sets = [];
            if ($this->colExists('venta', 'sync_uuid')) {
                $sets[] = 'sync_uuid = :u';
            }
            if ($this->colExists('venta', 'origen')) {
                $sets[] = "origen = 'local'";
            }
            if (!$sets) {
                return;
            }
            $st = $this->db->prepare('UPDATE venta SET ' . implode(', ', $sets) . ' WHERE id_venta = :id');
            $st->execute(['u' => $uuid, 'id' => $idVenta]);
        } catch (\Throwable $e) {
            // No fatal: la venta ya quedó creada; el uuid se puede estampar en el próximo pull.
        }
    }

    private function resolveSystemUserId(): int
    {
        // ecoruta_db usa `usuarios`, floracia_db usa `usuario`.
        foreach (['usuarios', 'usuario'] as $t) {
            try {
                $row = $this->db->query("SELECT id_usuario FROM `{$t}` ORDER BY id_usuario ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return (int)$row['id_usuario'];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return 0;
    }

    /**
     * Aplica UN pedido delivery de forma idempotente.
     * - Mismo sync_uuid ya aplicado -> `duplicado` (no inserta, no cobra dos veces).
     * - El local ya validó con el mismo código (createOrder); aquí se revalidan
     *   mínimos y se inserta tal cual con su tarifa/monto ya calculados.
     * - Regla de dinero: jamás revierte pagado=1 a 0 (ese caso ni siquiera
     *   llega aquí: el uuid existente responde duplicado antes de tocar nada).
     */
    private function applyOnePedido(array $p, int $usuarioId): array
    {
        $uuid = trim((string)($p['sync_uuid'] ?? ''));
        if ($uuid === '' || strlen($uuid) < 8) {
            return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'sync_uuid requerido'];
        }

        // Normaliza fechas (el agente puede mandar ISO '...T...Z'; MySQL exige 'Y-m-d H:i:s').
        foreach (['fecha_solicitud', 'fecha_pago'] as $fk) {
            if (isset($p[$fk]) && is_string($p[$fk])) {
                $norm = str_replace('T', ' ', substr($p[$fk], 0, 19));
                $p[$fk] = strtotime($norm) !== false ? date('Y-m-d H:i:s', strtotime($norm)) : null;
            }
        }

        try {
            if ($this->colExists('pedidos', 'sync_uuid')) {
                $st = $this->db->prepare('SELECT id_pedido, pagado, id_estado FROM pedidos WHERE sync_uuid = :u LIMIT 1');
                $st->execute(['u' => $uuid]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return [
                        'sync_uuid' => $uuid, 'status' => 'duplicado',
                        'id_pedido' => (int)$row['id_pedido'],
                        'message' => 'Ya existía (reintento seguro, no se duplicó ni se cobró dos veces)',
                    ];
                }
            }

            $origen = trim((string)($p['direccion_origen'] ?? ''));
            $destino = trim((string)($p['direccion_destino'] ?? ''));
            if ($origen === '' || $destino === '') {
                return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'Pedido sin dirección de origen o destino'];
            }

            // Comercio: el indicado si existe, si no el primero (igual que createOrder).
            $idComercio = (int)($p['id_comercio'] ?? 0);
            if ($idComercio > 0) {
                $chk = $this->db->prepare('SELECT 1 FROM comercios WHERE id_comercio = :id LIMIT 1');
                $chk->execute(['id' => $idComercio]);
                if (!$chk->fetchColumn()) {
                    $idComercio = 0;
                }
            }
            if ($idComercio <= 0) {
                $idComercio = (int)$this->db->query('SELECT id_comercio FROM comercios ORDER BY id_comercio ASC LIMIT 1')->fetchColumn();
                if ($idComercio <= 0) {
                    return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'No hay comercios en el servidor para asignar el pedido'];
                }
            }

            $metodo = (string)($p['metodo_pago'] ?? 'efectivo');
            if (!in_array($metodo, ['efectivo', 'transferencia', 'mixto'], true)) {
                $metodo = 'efectivo';
            }
            $pagado = (int)($p['pagado'] ?? 0) === 1 ? 1 : 0;

            // Saneos numéricos (mismo criterio que createOrder)
            $peso = (float)($p['peso_kg'] ?? 1.5);
            if ($peso <= 0 || $peso > 50) {
                $peso = 1.5;
            }
            $tarifa = max(0, (float)($p['tarifa_ecologica'] ?? 0));
            if ($tarifa <= 0) {
                return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'Pedido sin tarifa ecológica calculada'];
            }

            // Columnas según lo que tenga esta base (tolera deriva local/web)
            $vals = [
                'id_comercio' => $idComercio,
                'id_cliente' => isset($p['id_cliente']) && (int)$p['id_cliente'] > 0 ? (int)$p['id_cliente'] : null,
                'id_repartidor' => null, // la asignación viaja por su propio flujo tras el pull
                'id_estado' => 1,
                'direccion_origen' => $origen,
                'direccion_destino' => $destino,
                'detalle_paquete' => trim((string)($p['detalle_paquete'] ?? '')) !== '' ? trim((string)$p['detalle_paquete']) : 'Entrega Sustentable EcoRuta',
                'peso_kg' => $peso,
                'alto_cm' => isset($p['alto_cm']) && is_numeric($p['alto_cm']) ? (float)$p['alto_cm'] : null,
                'ancho_cm' => isset($p['ancho_cm']) && is_numeric($p['ancho_cm']) ? (float)$p['ancho_cm'] : null,
                'largo_cm' => isset($p['largo_cm']) && is_numeric($p['largo_cm']) ? (float)$p['largo_cm'] : null,
                'distancia_km' => isset($p['distancia_km']) ? (float)$p['distancia_km'] : null,
                'tarifa_ecologica' => $tarifa,
                'metodo_pago' => $metodo,
                'pagado' => $pagado,
                'fecha_pago' => $pagado ? (string)($p['fecha_pago'] ?? date('Y-m-d H:i:s')) : null,
                'comprobante_transferencia' => isset($p['comprobante_transferencia']) && trim((string)$p['comprobante_transferencia']) !== '' ? trim((string)$p['comprobante_transferencia']) : null,
                'monto_efectivo' => isset($p['monto_efectivo']) && is_numeric($p['monto_efectivo']) ? (float)$p['monto_efectivo'] : null,
                'monto_transferencia' => isset($p['monto_transferencia']) && is_numeric($p['monto_transferencia']) ? (float)$p['monto_transferencia'] : null,
                'monto_recibido' => isset($p['monto_recibido']) && is_numeric($p['monto_recibido']) ? (float)$p['monto_recibido'] : null,
                'vuelto' => isset($p['vuelto']) && is_numeric($p['vuelto']) ? (float)$p['vuelto'] : null,
                'co2_ahorrado_kg' => isset($p['co2_ahorrado_kg']) ? (float)$p['co2_ahorrado_kg'] : 0,
                'observaciones' => isset($p['observaciones']) ? trim((string)$p['observaciones'] . ' [sync local]') : '[sync local]',
                'fecha_solicitud' => (string)($p['fecha_solicitud'] ?? date('Y-m-d H:i:s')),
                'destinatario_nombre' => $p['destinatario_nombre'] ?? null,
                'destinatario_telefono' => $p['destinatario_telefono'] ?? null,
                'dest_lat' => isset($p['dest_lat']) && is_numeric($p['dest_lat']) ? (float)$p['dest_lat'] : null,
                'dest_lng' => isset($p['dest_lng']) && is_numeric($p['dest_lng']) ? (float)$p['dest_lng'] : null,
                'sync_uuid' => $uuid,
                'origen' => 'local',
            ];

            $cols = [];
            $phs = [];
            $params = [];
            foreach ($vals as $col => $val) {
                if (!$this->colExists('pedidos', $col)) {
                    continue;
                }
                $cols[] = "`$col`";
                $phs[] = ":$col";
                $params[$col] = $val;
            }
            if (!$cols) {
                return ['sync_uuid' => $uuid, 'status' => 'error', 'message' => 'Sin columnas compatibles en pedidos'];
            }

            $this->db->beginTransaction();
            try {
                $ins = $this->db->prepare('INSERT INTO pedidos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $phs) . ')');
                $ins->execute($params);
                $idPedido = (int)$this->db->lastInsertId();

                if ($this->tableExists('historial_estados')) {
                    $hist = $this->db->prepare(
                        'INSERT INTO historial_estados (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio, observacion)
                         VALUES (:pedido, NULL, 1, :user, :note)'
                    );
                    $hist->execute([
                        'pedido' => $idPedido,
                        'user' => $usuarioId > 0 ? $usuarioId : null,
                        'note' => 'Pedido sincronizado desde local (offline)',
                    ]);
                }
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }

            return [
                'sync_uuid' => $uuid, 'status' => 'applied',
                'id_pedido' => $idPedido,
                'message' => 'Pedido aplicado en el servidor',
            ];
        } catch (\Throwable $e) {
            return $this->conflictOrError($uuid, $e);
        }
    }

    private function colExists(string $table, string $col): bool
    {
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $st->execute(['t' => $table, 'c' => $col]);
        return (bool)$st->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $st->execute(['t' => $table]);
        return (bool)$st->fetchColumn();
    }
}
