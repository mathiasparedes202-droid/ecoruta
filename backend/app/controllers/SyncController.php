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
 * Garantías:
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
    public function pull(): void
    {
        try {
            if (!$this->tableExists('venta')) {
                $this->response->json(['success' => false, 'message' => 'Módulo de ventas no instalado en esta base (falta tabla venta)'], 501);
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

            // Detalles de esas ventas
            $ids = array_column($ventas, 'id_venta');
            $detalles = [];
            if ($ids) {
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
                $insumos = $this->db->query(
                    'SELECT id_insumo, stock FROM insumo'
                )->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $insumos = [];
            }

            $maxTs = $since;
            foreach ($ventas as $v) {
                if (!empty($v['_sync_ts']) && $v['_sync_ts'] > $maxTs) {
                    $maxTs = $v['_sync_ts'];
                }
            }

            $this->response->json([
                'success' => true,
                'data' => [
                    'ventas' => $ventas,
                    'detalles' => $detalles,
                    'stock_producto' => $stocks,
                    'insumos' => $insumos,
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
     * Body: { "ventas": [ {venta completa + detalles + sync_uuid}, ... ] }
     *
     * Respuesta por item:
     *  { sync_uuid, status: applied|duplicado|conflicto_folio_reasignado|conflicto_stock|error,
     *    id_venta, numero_factura, message }
     */
    public function push(): void
    {
        try {
            if (!$this->tableExists('venta')) {
                $this->response->json(['success' => false, 'message' => 'Módulo de ventas no instalado en esta base (falta tabla venta)'], 501);
                return;
            }
            $body = $this->request->getBody();
            $ventas = $body['ventas'] ?? $body['items'] ?? [];
            if (!is_array($ventas)) {
                $this->response->json(['success' => false, 'message' => 'ventas debe ser un arreglo'], 422);
                return;
            }
            if (count($ventas) > 100) {
                $this->response->json(['success' => false, 'message' => 'Máximo 100 ventas por lote'], 422);
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
        try {
            $row = $this->db->query('SELECT id_usuario FROM usuario ORDER BY id_usuario ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            return (int)($row['id_usuario'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
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
