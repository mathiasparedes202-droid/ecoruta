<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\Venta;

class VentaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    private function columnExists(string $table, string $column): bool
    {
        // `SHOW COLUMNS` does not support parameter binding for the LIKE clause
        // so we safely quote the column value and execute the query directly.
        $columnQuoted = $this->db->quote($column);
        $query = "SHOW COLUMNS FROM `{$table}` LIKE " . $columnQuoted;
        $stmt = $this->db->query($query);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeEstadoFactura(?string $estado): string
    {
        $estado = trim((string)($estado ?? ''));

        if ($estado === '') {
            return 'Vigente';
        }

        if (in_array($estado, ['Vigente', 'Anulada'], true)) {
            return $estado;
        }

        // Compatibilidad con instalaciones donde el estado de pago se guarda con el texto "Pagada".
        return 'Vigente';
    }

    public function all(): array
    {
        $query = "SELECT v.*, c.razon_social AS nombre_cliente, c.ruc_ci AS ruc_ci
                  FROM venta v
                  JOIN cliente c ON v.id_cliente = c.id_cliente
                  ORDER BY v.id_venta DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Venta($data), $rows);
    }

    public function findById(int $id): ?Venta
    {
        $query = "SELECT v.*, c.razon_social AS nombre_cliente, c.ruc_ci AS ruc_ci
                  FROM venta v
                  JOIN cliente c ON v.id_cliente = c.id_cliente
                  WHERE v.id_venta = :id
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new Venta($data) : null;
    }

    public function findWithDetails(int $id): ?array
    {
        // Obtener la venta
        $venta = $this->findById($id);
        if (!$venta) {
            return null;
        }

        // Obtener detalles. Algunas instalaciones guardan `id_producto` en la tabla,
        // otras guardan `id_insumo`. Hacemos LEFT JOIN a ambas tablas y usamos COALESCE
        // para obtener un nombre coherente en `nombre_producto`.
        $query = "SELECT dv.*, 
                         COALESCE(p.nombre_producto, i.nombre_insumo) AS nombre_producto,
                         p.descripcion AS descripcion_producto,
                         p.tipo_producto AS tipo_producto
                  FROM detalle_venta dv
                  LEFT JOIN producto p ON (dv.id_producto IS NOT NULL AND dv.id_producto = p.id_producto)
                  LEFT JOIN insumo i ON (dv.id_insumo IS NOT NULL AND dv.id_insumo = i.id_insumo)
                  WHERE dv.id_venta = :id_venta
                  ORDER BY dv.id_detalle_venta";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_venta', $id, PDO::PARAM_INT);
        $stmt->execute();

        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'venta' => $venta,
            'detalles' => $detalles
        ];
    }

    public function findByCliente(int $idCliente): array
    {
        $query = "SELECT v.*, c.razon_social AS nombre_cliente, c.ruc_ci AS ruc_ci
                  FROM venta v
                  JOIN cliente c ON v.id_cliente = c.id_cliente
                  WHERE v.id_cliente = :id_cliente
                  ORDER BY v.fecha_emision DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Venta($data), $rows);
    }

    public function findByProducto(int $idProducto): array
    {
        $query = "SELECT DISTINCT v.*, c.razon_social AS nombre_cliente, c.ruc_ci AS ruc_ci
                  FROM detalle_venta dv
                  JOIN venta v ON dv.id_venta = v.id_venta
                  JOIN cliente c ON v.id_cliente = c.id_cliente
                  WHERE dv.id_producto = :id_producto
                  ORDER BY v.fecha_emision DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Venta($data), $rows);
    }

    public function getNextNumeroFactura(): string
    {
        $query = "SELECT numero_factura FROM venta WHERE numero_factura IS NOT NULL AND numero_factura <> '' ORDER BY id_venta DESC LIMIT 1";
        $stmt = $this->db->query($query);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data || empty($data['numero_factura'])) {
            return '001-001-0000001';
        }

        $lastNumber = trim($data['numero_factura']);
        if (preg_match('/^(\d{3})-(\d{3})-(\d{7})$/', $lastNumber, $matches)) {
            $prefixA = $matches[1];
            $prefixB = $matches[2];
            $sequence = intval($matches[3]) + 1;
            return sprintf('%03d-%03d-%07d', $prefixA, $prefixB, $sequence);
        }

        return $lastNumber;
    }

    public function numeroFacturaExiste(string $numeroFactura, ?int $exceptoId = null): bool
    {
        $query = 'SELECT 1 FROM venta WHERE numero_factura = :numero_factura';
        $params = ['numero_factura' => $numeroFactura];

        if ($exceptoId !== null) {
            $query .= ' AND id_venta <> :id_venta';
            $params['id_venta'] = $exceptoId;
        }

        $query .= ' LIMIT 1';
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    public function create(array $ventaData, array $details): int
    {
        try {
            $this->db->beginTransaction();

            $query = "INSERT INTO venta (
                        id_cliente,
                        id_pedido,
                        numero_factura,
                        timbrado,
                        fecha_emision,
                        estado_factura,
                        tipo_comprobante,
                        numero_comprobante,
                        delivery_option,
                        direccion_entrega,
                        observaciones,
                        condicion_venta,
                        plazo,
                        total_factura,
                        iva_5,
                        iva_10,
                        subtotal_iva_exenta,
                        subtotal_iva_5,
                        subtotal_iva_10,
                        total_iva,
                        liquidacion_iva_5,
                        liquidacion_iva_10,
                        total_liquidacion,
                        creado_por
                      ) VALUES (
                        :id_cliente,
                        :id_pedido,
                        :numero_factura,
                        :timbrado,
                        :fecha_emision,
                        :estado_factura,
                        :tipo_comprobante,
                        :numero_comprobante,
                        :delivery_option,
                        :direccion_entrega,
                        :observaciones,
                        :condicion_venta,
                        :plazo,
                        :total_factura,
                        :iva_5,
                        :iva_10,
                        :subtotal_iva_exenta,
                        :subtotal_iva_5,
                        :subtotal_iva_10,
                        :total_iva,
                        :liquidacion_iva_5,
                        :liquidacion_iva_10,
                        :total_liquidacion,
                        :creado_por
                      )";

            $estadoFactura = $this->normalizeEstadoFactura($ventaData['estado_factura'] ?? 'Vigente');

            $stmt = $this->db->prepare($query);
            $success = $stmt->execute([
                'id_cliente' => $ventaData['id_cliente'],
                'id_pedido' => $ventaData['id_pedido'] ?? null,
                'numero_factura' => $ventaData['numero_factura'],
                'timbrado' => $ventaData['timbrado'],
                'fecha_emision' => $ventaData['fecha_emision'],
                'estado_factura' => $estadoFactura,
                'tipo_comprobante' => $ventaData['tipo_comprobante'] ?? 'Factura',
                'numero_comprobante' => $ventaData['numero_comprobante'] ?? null,
                'delivery_option' => $ventaData['delivery_option'] ?? 'Retiro',
                'direccion_entrega' => $ventaData['direccion_entrega'] ?? null,
                'observaciones' => $ventaData['observaciones'] ?? null,
                'condicion_venta' => $ventaData['condicion_venta'] ?? 'Contado',
                'plazo' => $ventaData['plazo'] ?? null,
                'total_factura' => $ventaData['total_factura'] ?? 0,
                'iva_5' => $ventaData['iva_5'] ?? 0,
                'iva_10' => $ventaData['iva_10'] ?? 0,
                'subtotal_iva_exenta' => $ventaData['subtotal_iva_exenta'] ?? 0,
                'subtotal_iva_5' => $ventaData['subtotal_iva_5'] ?? 0,
                'subtotal_iva_10' => $ventaData['subtotal_iva_10'] ?? 0,
                'total_iva' => $ventaData['total_iva'] ?? 0,
                'liquidacion_iva_5' => $ventaData['liquidacion_iva_5'] ?? 0,
                'liquidacion_iva_10' => $ventaData['liquidacion_iva_10'] ?? 0,
                'total_liquidacion' => $ventaData['total_liquidacion'] ?? 0,
                'creado_por' => $ventaData['creado_por'],
            ]);

            if (!$success) {
                $err = $stmt->errorInfo();
                throw new \Exception('Error al crear la venta: ' . ($err[2] ?? json_encode($err)) . " -- SQL: " . $query . " -- PARAMS: " . json_encode([
                    'id_cliente' => $ventaData['id_cliente'] ?? null,
                    'id_pedido' => $ventaData['id_pedido'] ?? null,
                    'numero_factura' => $ventaData['numero_factura'] ?? null,
                    'creado_por' => $ventaData['creado_por'] ?? null,
                ], JSON_UNESCAPED_UNICODE));
            }

            $ventaId = (int)$this->db->lastInsertId();

            // Insertar detalles de la venta.
            // El sistema históricamente ha usado `id_insumo` en la tabla `detalle_venta`,
            // mientras que las versiones más recientes usan `id_producto` y columnas
            // adicionales como `detalle_produccion` / `detalle_receta`.

            $hasIdProducto = $this->columnExists('detalle_venta', 'id_producto');
            $hasIdInsumo = $this->columnExists('detalle_venta', 'id_insumo');
            $hasDetalleProduccion = $this->columnExists('detalle_venta', 'detalle_produccion');
            $hasDetalleReceta = $this->columnExists('detalle_venta', 'detalle_receta');

            $detailQueryProducto = null;
            $detailQueryInsumo = null;

            if ($hasIdProducto) {
                $columns = ['id_venta', 'id_producto', 'cantidad', 'precio_unitario', 'iva_tipo', 'subtotal'];
                $params = [':id_venta', ':id_producto', ':cantidad', ':precio_unitario', ':iva_tipo', ':subtotal'];

                if ($hasDetalleProduccion) {
                    $columns[] = 'detalle_produccion';
                    $params[] = ':detalle_produccion';
                }
                if ($hasDetalleReceta) {
                    $columns[] = 'detalle_receta';
                    $params[] = ':detalle_receta';
                }

                $columns[] = 'creado_por';
                $params[] = ':creado_por';

                $detailQueryProducto = sprintf(
                    "INSERT INTO detalle_venta (%s) VALUES (%s)",
                    implode(', ', $columns),
                    implode(', ', $params)
                );
            }

            if ($hasIdInsumo) {
                $columns = ['id_venta', 'id_insumo', 'cantidad', 'precio_unitario', 'iva_tipo', 'subtotal'];
                $params = [':id_venta', ':id_insumo', ':cantidad', ':precio_unitario', ':iva_tipo', ':subtotal'];

                if ($hasDetalleProduccion) {
                    $columns[] = 'detalle_produccion';
                    $params[] = ':detalle_produccion';
                }
                if ($hasDetalleReceta) {
                    $columns[] = 'detalle_receta';
                    $params[] = ':detalle_receta';
                }

                $columns[] = 'creado_por';
                $params[] = ':creado_por';

                $detailQueryInsumo = sprintf(
                    "INSERT INTO detalle_venta (%s) VALUES (%s)",
                    implode(', ', $columns),
                    implode(', ', $params)
                );
            }

            if (!$detailQueryProducto && !$detailQueryInsumo) {
                throw new \Exception('La tabla detalle_venta no tiene columnas compatibles para guardar el detalle de la venta.');
            }

            try {
                $detailStmtProducto = $detailQueryProducto ? $this->db->prepare($detailQueryProducto) : null;
            } catch (\Throwable $e) {
                $logPath = __DIR__ . '/../../storage/logs/venta_debug.log';
                $msg = '[' . date('Y-m-d H:i:s') . '] Error al preparar stmt producto: ' . $e->getMessage() . "\nSQL: " . ($detailQueryProducto ?? '') . "\n\n";
                @file_put_contents($logPath, $msg, FILE_APPEND);
                throw $e;
            }

            try {
                $detailStmtInsumo = $detailQueryInsumo ? $this->db->prepare($detailQueryInsumo) : null;
            } catch (\Throwable $e) {
                $logPath = __DIR__ . '/../../storage/logs/venta_debug.log';
                $msg = '[' . date('Y-m-d H:i:s') . '] Error al preparar stmt insumo: ' . $e->getMessage() . "\nSQL: " . ($detailQueryInsumo ?? '') . "\n\n";
                @file_put_contents($logPath, $msg, FILE_APPEND);
                throw $e;
            }

            foreach ($details as $detail) {
                $paramsCommon = [
                    'id_venta' => $ventaId,
                    'cantidad' => $detail['cantidad'],
                    'precio_unitario' => $detail['precio_unitario'],
                    'iva_tipo' => $detail['iva_tipo'] ?? 10,
                    'subtotal' => $detail['subtotal'] ?? ($detail['cantidad'] * $detail['precio_unitario']),
                    'detalle_produccion' => $detail['detalle_produccion'] ?? null,
                    'detalle_receta' => !empty($detail['receta']) ? json_encode($detail['receta'], JSON_UNESCAPED_UNICODE) : null,
                    'creado_por' => $ventaData['creado_por'],
                ];

                $hasProductoId = !empty($detail['id_producto']);
                $hasInsumoId = !empty($detail['id_insumo']);
                $success = false;

                if ($hasProductoId) {
                    if (!$detailStmtProducto) {
                        throw new \Exception('La tabla detalle_venta no tiene la columna id_producto. Ejecuta la migración de esquema de ventas.');
                    }

                    $params = $paramsCommon;
                    $params['id_producto'] = (int)$detail['id_producto'];
                    try {
                        $success = $detailStmtProducto->execute($params);
                    } catch (\Throwable $e) {
                        $logPath = __DIR__ . '/../../storage/logs/venta_debug.log';
                        $msg = '[' . date('Y-m-d H:i:s') . '] Detalle producto error: ' . $e->getMessage() . "\nSQL: " . $detailQueryProducto . "\nPARAMS: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n\n";
                        @file_put_contents($logPath, $msg, FILE_APPEND);
                        throw $e;
                    }
                } elseif ($hasInsumoId) {
                    if (!$detailStmtInsumo) {
                        throw new \Exception('La tabla detalle_venta no tiene la columna id_insumo. Verifica la migración de esquema de ventas.');
                    }

                    $params = $paramsCommon;
                    $params['id_insumo'] = (int)$detail['id_insumo'];
                    try {
                        $success = $detailStmtInsumo->execute($params);
                    } catch (\Throwable $e) {
                        $logPath = __DIR__ . '/../../storage/logs/venta_debug.log';
                        $msg = '[' . date('Y-m-d H:i:s') . '] Detalle insumo error: ' . $e->getMessage() . "\nSQL: " . $detailQueryInsumo . "\nPARAMS: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n\n";
                        @file_put_contents($logPath, $msg, FILE_APPEND);
                        throw $e;
                    }
                } else {
                    throw new \Exception('Detalle de venta sin id_producto ni id_insumo válido');
                }

                if (!$success) {
                    throw new \Exception('No se pudieron guardar los detalles de la venta');
                }
            }

            $this->db->commit();

            return $ventaId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getDetallesByVentaId(int $ventaId): array
    {
        $query = 'SELECT id_insumo, cantidad FROM detalle_venta WHERE id_venta = :id_venta';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_venta', $ventaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['numero_factura'])) {
            $fields[] = 'numero_factura = :numero_factura';
            $params['numero_factura'] = $data['numero_factura'];
        }

        if (isset($data['timbrado'])) {
            $fields[] = 'timbrado = :timbrado';
            $params['timbrado'] = $data['timbrado'];
        }

        if (array_key_exists('numero_comprobante', $data)) {
            $fields[] = 'numero_comprobante = :numero_comprobante';
            $params['numero_comprobante'] = $data['numero_comprobante'];
        }

        if (array_key_exists('estado_factura', $data)) {
            $fields[] = 'estado_factura = :estado_factura';
            $params['estado_factura'] = $this->normalizeEstadoFactura($data['estado_factura']);
        }

        if (isset($data['delivery_option'])) {
            $fields[] = 'delivery_option = :delivery_option';
            $params['delivery_option'] = $data['delivery_option'];
        }

        if (array_key_exists('direccion_entrega', $data)) {
            $fields[] = 'direccion_entrega = :direccion_entrega';
            $params['direccion_entrega'] = $data['direccion_entrega'];
        }

        if (array_key_exists('observaciones', $data)) {
            $fields[] = 'observaciones = :observaciones';
            $params['observaciones'] = $data['observaciones'];
        }

        if (isset($data['total_factura'])) {
            $fields[] = 'total_factura = :total_factura';
            $params['total_factura'] = $data['total_factura'];
        }

        // Permitir actualizar referencia a pedido de producción
        if (isset($data['id_pedido'])) {
            $fields[] = 'id_pedido = :id_pedido';
            $params['id_pedido'] = $data['id_pedido'];
        }

        if (empty($fields)) {
            return false;
        }

        $query = 'UPDATE venta SET ' . implode(', ', $fields) . ' WHERE id_venta = :id';
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Actualiza el campo detalle_produccion en los detalles de una venta para un producto
     */
    public function setDetalleProduccionForVentaProduct(int $ventaId, int $idProducto, string $nota): bool
    {
        $query = 'UPDATE detalle_venta SET detalle_produccion = :nota WHERE id_venta = :id_venta AND id_producto = :id_producto';
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'nota' => $nota,
            'id_venta' => $ventaId,
            'id_producto' => $idProducto,
        ]);
    }

    public function delete(int $id): bool
    {
        try {
            $this->db->beginTransaction();

            // Eliminar detalles
            $this->deleteDetallesById($id);

            // Eliminar venta
            $query = 'DELETE FROM venta WHERE id_venta = :id';
            $stmt = $this->db->prepare($query);
            $success = $stmt->execute(['id' => $id]);

            if (!$success) {
                throw new \Exception('No se pudo eliminar la venta');
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteDetallesById(int $ventaId): bool
    {
        $query = 'DELETE FROM detalle_venta WHERE id_venta = :id_venta';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_venta', $ventaId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
