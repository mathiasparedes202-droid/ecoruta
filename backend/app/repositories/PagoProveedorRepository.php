<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\PagoProveedor;

class PagoProveedorRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function all(): array
    {
        $query = "SELECT ppd.id_detalle, ppd.*, ppd.metodo_pago AS tipo_pago, ppc.fecha_pago, ppc.estado, ppc.observaciones, ppc.fecha_creacion, ppc.creado_por,
                         c.numero_factura, c.condicion_compra, prov.razon_social AS proveedor,
                         CONCAT(u.nombre, ' ', u.apellido) AS usuario_nombre
                  FROM pago_proveedor_detalle ppd
                  JOIN pago_proveedor ppc ON ppd.id_pago_proveedor = ppc.id_pago_proveedor
                  LEFT JOIN compra c ON ppc.id_compra = c.id_compra
                  LEFT JOIN proveedor prov ON c.id_proveedor = prov.id_proveedor
                  LEFT JOIN usuario u ON ppc.creado_por = u.id_usuario
                  ORDER BY ppc.fecha_pago DESC, ppd.id_detalle DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new PagoProveedor($data), $rows);
    }

    public function create(array $data): int
    {
        $detalles = $data['detalles'] ?? [
            [
                'metodo_pago' => $data['metodo_pago'] ?? 'Efectivo',
                'monto_pagado' => $data['monto'] ?? 0,
                'numero_comprobante' => $data['numero_comprobante'] ?? null,
                'banco' => $data['banco'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'estado_pago' => $data['estado_pago'] ?? 'Confirmado',
                'observaciones' => $data['observaciones'] ?? null,
            ],
        ];

        $montoTotal = array_sum(array_map(fn($item) => (float)($item['monto_pagado'] ?? 0), $detalles));

        // Crear cabecera
        $cabeceraQuery = "INSERT INTO pago_proveedor
                         (id_compra, fecha_pago, monto_total, tipo_pago_general, estado, observaciones, creado_por)
                         VALUES (:id_compra, :fecha_pago, :monto_total, :tipo_pago_general, 'Confirmado', :observaciones, :creado_por)";

        $stmt = $this->db->prepare($cabeceraQuery);
        $stmt->execute([
            'id_compra' => $data['id_compra'],
            'fecha_pago' => $data['fecha_pago'],
            'monto_total' => $montoTotal,
            'tipo_pago_general' => $data['tipo_pago_general'] ?? 'Compra',
            'observaciones' => $data['observaciones'] ?? null,
            'creado_por' => $data['creado_por'],
        ]);

        $idPago = (int)$this->db->lastInsertId();

        $detalleQuery = "INSERT INTO pago_proveedor_detalle
                        (id_pago_proveedor, metodo_pago, monto_pagado, numero_comprobante, banco, fecha_vencimiento, estado_pago, observaciones, cantidad_cuotas, numero_cuota, monto_cuota, saldo_pendiente, creado_por)
                        VALUES (:id_pago_proveedor, :metodo_pago, :monto_pagado, :numero_comprobante, :banco, :fecha_vencimiento, :estado_pago, :observaciones, :cantidad_cuotas, :numero_cuota, :monto_cuota, :saldo_pendiente, :creado_por)";

        $stmt = $this->db->prepare($detalleQuery);
        foreach ($detalles as $detalle) {
            $stmt->execute([
                'id_pago_proveedor' => $idPago,
                'metodo_pago' => $detalle['metodo_pago'] ?? 'Efectivo',
                'monto_pagado' => $detalle['monto_pagado'] ?? 0,
                'numero_comprobante' => $detalle['numero_comprobante'] ?? null,
                'banco' => $detalle['banco'] ?? null,
                'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                'estado_pago' => $detalle['estado_pago'] ?? 'Confirmado',
                'observaciones' => $detalle['observaciones'] ?? null,
                'cantidad_cuotas' => $detalle['cantidad_cuotas'] ?? null,
                'numero_cuota' => $detalle['numero_cuota'] ?? null,
                'monto_cuota' => $detalle['monto_cuota'] ?? null,
                'saldo_pendiente' => $detalle['saldo_pendiente'] ?? 0,
                'creado_por' => $data['creado_por'],
            ]);
        }

        return $idPago;
    }

    public function findById(int $id): ?PagoProveedor
    {
        $query = "SELECT ppd.id_detalle, ppd.*, ppd.metodo_pago AS tipo_pago, ppc.fecha_pago, ppc.estado, ppc.observaciones, ppc.fecha_creacion, ppc.creado_por, ppc.monto_total, ppc.tipo_pago_general,
                         c.numero_factura, prov.razon_social AS proveedor
                  FROM pago_proveedor_detalle ppd
                  JOIN pago_proveedor ppc ON ppd.id_pago_proveedor = ppc.id_pago_proveedor
                  LEFT JOIN compra c ON ppc.id_compra = c.id_compra
                  LEFT JOIN proveedor prov ON c.id_proveedor = prov.id_proveedor
                  WHERE ppd.id_detalle = :id";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new PagoProveedor($data) : null;
    }

    public function findByPagoId(int $idPago): ?PagoProveedor
    {
        $query = "SELECT ppd.id_detalle, ppd.*, ppd.metodo_pago AS tipo_pago, ppc.fecha_pago, ppc.estado, ppc.observaciones, ppc.fecha_creacion, ppc.creado_por, ppc.monto_total, ppc.tipo_pago_general,
                         c.numero_factura, prov.razon_social AS proveedor
                  FROM pago_proveedor_detalle ppd
                  JOIN pago_proveedor ppc ON ppd.id_pago_proveedor = ppc.id_pago_proveedor
                  LEFT JOIN compra c ON ppc.id_compra = c.id_compra
                  LEFT JOIN proveedor prov ON c.id_proveedor = prov.id_proveedor
                  WHERE ppc.id_pago_proveedor = :id_pago
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['id_pago' => $idPago]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new PagoProveedor($data) : null;
    }

    public function delete(int $id): bool
    {
        // Primero obtener el id_pago_proveedor de la cabecera
        $query = "SELECT id_pago_proveedor FROM pago_proveedor_detalle WHERE id_detalle = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        $idPago = $result['id_pago_proveedor'];

        // Eliminar detalle
        $query = "DELETE FROM pago_proveedor_detalle WHERE id_detalle = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);

        // Verificar si quedan más detalles para esta cabecera
        $query = "SELECT COUNT(*) as count FROM pago_proveedor_detalle WHERE id_pago_proveedor = :id_pago_proveedor";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id_pago_proveedor' => $idPago]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Si no quedan detalles, eliminar la cabecera
        if ($count == 0) {
            $query = "DELETE FROM pago_proveedor WHERE id_pago_proveedor = :id_pago_proveedor";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id_pago_proveedor' => $idPago]);
        }

        return true;
    }

    public function getTotalPagadoPorCompra(int $idCompra): float
    {
        $query = "SELECT COALESCE(SUM(ppd.monto_pagado), 0) as total_pagado
                  FROM pago_proveedor_detalle ppd
                  JOIN pago_proveedor ppc ON ppd.id_pago_proveedor = ppc.id_pago_proveedor
                  WHERE ppc.id_compra = :id_compra AND ppc.estado = 'Confirmado'";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['id_compra' => $idCompra]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)$result['total_pagado'];
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        $this->db->rollback();
    }
}
