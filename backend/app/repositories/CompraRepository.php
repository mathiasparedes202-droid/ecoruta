<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\Compra;

class CompraRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function all(): array
    {
        $query = "SELECT c.*, p.razon_social AS nombre_proveedor
                  FROM compra c
                  JOIN proveedor p ON c.id_proveedor = p.id_proveedor
                  ORDER BY c.id_compra DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Compra($data), $rows);
    }

    public function findById(int $id): ?Compra
    {
        $query = "SELECT c.*, p.razon_social AS nombre_proveedor, p.ruc AS ruc_proveedor
                  FROM compra c
                  JOIN proveedor p ON c.id_proveedor = p.id_proveedor
                  WHERE c.id_compra = :id
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new Compra($data) : null;
    }

    public function findWithDetails(int $id): ?array
    {
        // Obtener la compra
        $compra = $this->findById($id);
        if (!$compra) {
            return null;
        }

        // Obtener detalles
        $query = "SELECT cd.*, i.nombre_insumo, i.descripcion, u.nombre_unidad
                  FROM compra_detalle cd
                  JOIN insumo i ON cd.id_insumo = i.id_insumo
                  LEFT JOIN unidad u ON i.id_unidad = u.id_unidad
                  WHERE cd.id_compra = :id_compra
                  ORDER BY cd.id_detalle";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_compra', $id, PDO::PARAM_INT);
        $stmt->execute();

        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'compra' => $compra,
            'detalles' => $detalles
        ];
    }

    public function findByProveedor(int $idProveedor): array
    {
        $query = "SELECT c.*, p.razon_social AS nombre_proveedor
                  FROM compra c
                  JOIN proveedor p ON c.id_proveedor = p.id_proveedor
                  WHERE c.id_proveedor = :id_proveedor
                  ORDER BY c.fecha_emision DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_proveedor', $idProveedor, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Compra($data), $rows);
    }

    public function findByInsumo(int $idInsumo): array
    {
        $query = "SELECT DISTINCT c.*, p.razon_social AS nombre_proveedor
                  FROM compra_detalle cd
                  JOIN compra c ON cd.id_compra = c.id_compra
                  JOIN proveedor p ON c.id_proveedor = p.id_proveedor
                  WHERE cd.id_insumo = :id_insumo
                  ORDER BY c.fecha_emision DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_insumo', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Compra($data), $rows);
    }

    public function create(array $compraData, array $details): int
    {
        try {
            $this->db->beginTransaction();

            $query = "INSERT INTO compra (
                        id_proveedor,
                        numero_factura,
                        timbrado,
                        fecha_emision,
                        estado_factura,
                        tipo_comprobante,
                        condicion_compra,
                        plazo,
                        observaciones,
                        total_compra,
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
                        :id_proveedor,
                        :numero_factura,
                        :timbrado,
                        :fecha_emision,
                        :estado_factura,
                        :tipo_comprobante,
                        :condicion_compra,
                        :plazo,
                        :observaciones,
                        :total_compra,
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

            $stmt = $this->db->prepare($query);
            $success = $stmt->execute([
                'id_proveedor' => $compraData['id_proveedor'],
                'numero_factura' => $compraData['numero_factura'],
                'timbrado' => $compraData['timbrado'],
                'fecha_emision' => $compraData['fecha_emision'],
                'estado_factura' => $compraData['estado_factura'],
                'tipo_comprobante' => $compraData['tipo_comprobante'],
                'condicion_compra' => $compraData['condicion_compra'],
                'plazo' => $compraData['plazo'],
                'observaciones' => $compraData['observaciones'],
                'total_compra' => $compraData['total_compra'],
                'iva_5' => $compraData['iva_5'],
                'iva_10' => $compraData['iva_10'],
                'subtotal_iva_exenta' => $compraData['subtotal_iva_exenta'],
                'subtotal_iva_5' => $compraData['subtotal_iva_5'],
                'subtotal_iva_10' => $compraData['subtotal_iva_10'],
                'total_iva' => $compraData['total_iva'],
                'liquidacion_iva_5' => $compraData['liquidacion_iva_5'],
                'liquidacion_iva_10' => $compraData['liquidacion_iva_10'],
                'total_liquidacion' => $compraData['total_liquidacion'],
                'creado_por' => $compraData['creado_por'],
            ]);

            if (!$success) {
                throw new \Exception('No se pudo crear la cabecera de la compra');
            }

            $compraId = (int)$this->db->lastInsertId();

            // Insertar detalles de la compra
            $detailQuery = "INSERT INTO compra_detalle (
                                id_compra,
                                id_insumo,
                                cantidad,
                                precio_unitario,
                                subtotal
                              ) VALUES (
                                :id_compra,
                                :id_insumo,
                                :cantidad,
                                :precio_unitario,
                                :subtotal
                              )";

            $detailStmt = $this->db->prepare($detailQuery);

            foreach ($details as $detail) {
                $success = $detailStmt->execute([
                    'id_compra' => $compraId,
                    'id_insumo' => $detail['id_insumo'],
                    'cantidad' => $detail['cantidad'],
                    'precio_unitario' => $detail['precio_unitario'],
                    'subtotal' => $detail['subtotal'] ?? ($detail['cantidad'] * $detail['precio_unitario']),
                ]);

                if (!$success) {
                    throw new \Exception('No se pudieron guardar los detalles de la compra');
                }
            }

            $this->db->commit();

            return $compraId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['id_proveedor'])) {
            $fields[] = 'id_proveedor = :id_proveedor';
            $params['id_proveedor'] = $data['id_proveedor'];
        }

        if (isset($data['numero_factura'])) {
            $fields[] = 'numero_factura = :numero_factura';
            $params['numero_factura'] = $data['numero_factura'];
        }

        if (isset($data['timbrado'])) {
            $fields[] = 'timbrado = :timbrado';
            $params['timbrado'] = $data['timbrado'];
        }

        if (isset($data['fecha_emision'])) {
            $fields[] = 'fecha_emision = :fecha_emision';
            $params['fecha_emision'] = $data['fecha_emision'];
        }

        if (array_key_exists('observaciones', $data)) {
            $fields[] = 'observaciones = :observaciones';
            $params['observaciones'] = $data['observaciones'];
        }

        if (isset($data['total_compra'])) {
            $fields[] = 'total_compra = :total_compra';
            $params['total_compra'] = $data['total_compra'];
        }

        if (isset($data['total_iva'])) {
            $fields[] = 'total_iva = :total_iva';
            $params['total_iva'] = $data['total_iva'];
        }

        if (empty($fields)) {
            return false;
        }

        $query = 'UPDATE compra SET ' . implode(', ', $fields) . ' WHERE id_compra = :id';
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function deleteDetallesByCompraId(int $compraId): bool
    {
        $query = 'DELETE FROM compra_detalle WHERE id_compra = :id_compra';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_compra', $compraId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function insertDetalles(int $compraId, array $details): bool
    {
        $query = "INSERT INTO compra_detalle (id_compra, id_insumo, cantidad, precio_unitario, subtotal) VALUES (:id_compra, :id_insumo, :cantidad, :precio_unitario, :subtotal)";
        $stmt = $this->db->prepare($query);

        foreach ($details as $detail) {
            $subtotal = ($detail['cantidad'] * $detail['precio_unitario']) - ($detail['descuento'] ?? 0);
            $success = $stmt->execute([
                'id_compra' => $compraId,
                'id_insumo' => $detail['id_insumo'],
                'cantidad' => $detail['cantidad'],
                'precio_unitario' => $detail['precio_unitario'],
                'subtotal' => $subtotal,
            ]);

            if (!$success) {
                return false;
            }
        }

        return true;
    }

    public function anular(int $id, int $usuarioId, ?string $motivo = null): bool
    {
        $query = "UPDATE compra SET estado_factura = 'Anulada', fecha_anulacion = NOW(), anulado_por = :usuario";

        if ($motivo !== null) {
            $query .= ", motivo_anulacion = :motivo";
        }

        $query .= " WHERE id_compra = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':usuario', $usuarioId, PDO::PARAM_INT);

        if ($motivo !== null) {
            $stmt->bindParam(':motivo', $motivo, PDO::PARAM_STR);
        }

        return $stmt->execute();
    }

    public function getDetallesByCompraId(int $compraId): array
    {
        $query = "SELECT id_detalle, id_compra, id_insumo, cantidad, precio_unitario, subtotal
                  FROM compra_detalle
                  WHERE id_compra = :id_compra";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_compra', $compraId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function findComprasVigentes(): array
    {
        $query = "SELECT c.*, p.razon_social AS nombre_proveedor, p.ruc AS ruc_proveedor
                  FROM compra c
                  JOIN proveedor p ON c.id_proveedor = p.id_proveedor
                  WHERE c.estado_factura NOT IN ('Anulada', 'Pagada')
                  ORDER BY c.fecha_emision DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Compra($data), $rows);
    }

    public function actualizarEstadoPago(int $idCompra, float $montoPagado, int $usuarioId): bool
    {
        // Obtener la compra actual
        $compra = $this->findById($idCompra);
        if (!$compra) {
            return false;
        }

        // Determinar el nuevo estado de pago
        $nuevoEstadoPago = 'No pagada';
        if ($montoPagado > 0 && $montoPagado < $compra->total_compra) {
            $nuevoEstadoPago = 'Pagada parcial';
        } elseif ($montoPagado >= $compra->total_compra) {
            $nuevoEstadoPago = 'Pagada';
        }

        // Determinar el nuevo estado de factura si se paga completamente
        $nuevoEstadoFactura = $compra->estado_factura;
        if ($nuevoEstadoPago === 'Pagada' && $compra->estado_factura === 'Vigente') {
            $nuevoEstadoFactura = 'Pagada';
        }

        // Actualizar la compra
        $query = "UPDATE compra SET
                  estado_pago = :estado_pago,
                  estado_factura = :estado_factura,
                  monto_pagado = :monto_pagado,
                  fecha_ultima_modificacion = NOW(),
                  modificado_por = :modificado_por
                  WHERE id_compra = :id_compra";

        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'estado_pago' => $nuevoEstadoPago,
            'estado_factura' => $nuevoEstadoFactura,
            'monto_pagado' => $montoPagado,
            'modificado_por' => $usuarioId,
            'id_compra' => $idCompra,
        ]);
    }

    public function getTotalPagadoPorCompra(int $idCompra): float
    {
        $query = "SELECT COALESCE(SUM(pp.monto_total), 0) as total_pagado
                  FROM pago_proveedor pp
                  WHERE pp.id_compra = :id_compra AND pp.estado = 'Confirmado'";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_compra', $idCompra, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($result['total_pagado'] ?? 0);
    }
}
