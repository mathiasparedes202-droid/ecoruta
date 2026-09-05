<?php

namespace App\Repositories;

use Config\Database;
use PDO;

class PagoClienteRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
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

    public function getTotalPagadoPorVenta(int $idVenta): float
    {
        $query = "SELECT COALESCE(SUM(monto_total), 0) AS total_pagado
                  FROM pago_cliente_cabecera
                  WHERE id_venta = :id_venta AND estado = 'Confirmado'";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($result['total_pagado'] ?? 0);
    }

    public function findByVenta(int $idVenta): array
    {
        $query = "SELECT pc.*, u.nombre AS cobrador_nombre, u.apellido AS cobrador_apellido
                  FROM pago_cliente_cabecera pc
                  LEFT JOIN usuario u ON pc.creado_por = u.id_usuario
                  WHERE pc.id_venta = :id_venta
                  ORDER BY pc.fecha_pago DESC, pc.id_pago DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        foreach ($rows as $r) {
            $idPago = (int)$r['id_pago'];
            // Obtener detalles
            $detQuery = "SELECT * FROM pago_cliente_detalle WHERE id_pago = :id_pago ORDER BY id_pago_detalle";
            $detStmt = $this->db->prepare($detQuery);
            $detStmt->bindParam(':id_pago', $idPago, PDO::PARAM_INT);
            $detStmt->execute();
            $detalles = $detStmt->fetchAll(PDO::FETCH_ASSOC);

            $r['detalles'] = $detalles;
            $r['cobrador'] = isset($r['cobrador_nombre']) ? trim(($r['cobrador_nombre'] ?? '') . ' ' . ($r['cobrador_apellido'] ?? '')) : null;
            $results[] = $r;
        }

        return $results;
    }

    public function findById(int $idPago): ?array
    {
        $query = "SELECT pc.*, u.nombre AS cobrador_nombre, u.apellido AS cobrador_apellido
                  FROM pago_cliente_cabecera pc
                  LEFT JOIN usuario u ON pc.creado_por = u.id_usuario
                  WHERE pc.id_pago = :id_pago
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_pago', $idPago, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) return null;

        $detQuery = "SELECT * FROM pago_cliente_detalle WHERE id_pago = :id_pago ORDER BY id_pago_detalle";
        $detStmt = $this->db->prepare($detQuery);
        $detStmt->bindParam(':id_pago', $idPago, PDO::PARAM_INT);
        $detStmt->execute();
        $detalles = $detStmt->fetchAll(PDO::FETCH_ASSOC);

        $data['detalles'] = $detalles;
        $data['cobrador'] = isset($data['cobrador_nombre']) ? trim(($data['cobrador_nombre'] ?? '') . ' ' . ($data['cobrador_apellido'] ?? '')) : null;

        return $data;
    }

    public function createPayment(array $cabecera, array $detalles, int $usuarioId): int
    {
        $query = "INSERT INTO pago_cliente_cabecera (id_venta, fecha_pago, monto_total, tipo_pago_general, estado, observaciones, creado_por)
                  VALUES (:id_venta, :fecha_pago, :monto_total, :tipo_pago_general, :estado, :observaciones, :creado_por)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'id_venta' => $cabecera['id_venta'],
            'fecha_pago' => $cabecera['fecha_pago'] ?? null,
            'monto_total' => $cabecera['monto_total'] ?? 0,
            'tipo_pago_general' => $cabecera['tipo_pago_general'] ?? 'Venta',
            'estado' => $cabecera['estado'] ?? 'Confirmado',
            'observaciones' => $cabecera['observaciones'] ?? null,
            'creado_por' => $usuarioId,
        ]);

        $idPago = (int)$this->db->lastInsertId();

        // Detectar si la columna `banco` existe en la tabla de detalle
        $stmtCol = $this->db->prepare("SHOW COLUMNS FROM `pago_cliente_detalle` LIKE 'banco'");
        $stmtCol->execute();
        $hasBanco = (bool)$stmtCol->fetch(PDO::FETCH_ASSOC);

        if ($hasBanco) {
            $detailQuery = "INSERT INTO pago_cliente_detalle (id_pago, tipo_pago, monto_pagado, numero_comprobante, banco, cantidad_cuotas, numero_cuota, monto_cuota, fecha_vencimiento, saldo_pendiente, creado_por)\n                        VALUES (:id_pago, :tipo_pago, :monto_pagado, :numero_comprobante, :banco, :cantidad_cuotas, :numero_cuota, :monto_cuota, :fecha_vencimiento, :saldo_pendiente, :creado_por)";
        } else {
            $detailQuery = "INSERT INTO pago_cliente_detalle (id_pago, tipo_pago, monto_pagado, numero_comprobante, cantidad_cuotas, numero_cuota, monto_cuota, fecha_vencimiento, saldo_pendiente, creado_por)\n                        VALUES (:id_pago, :tipo_pago, :monto_pagado, :numero_comprobante, :cantidad_cuotas, :numero_cuota, :monto_cuota, :fecha_vencimiento, :saldo_pendiente, :creado_por)";
        }

        $detailStmt = $this->db->prepare($detailQuery);

        foreach ($detalles as $d) {
            $params = [
                'id_pago' => $idPago,
                'tipo_pago' => $d['tipo_pago'] ?? 'Efectivo',
                'monto_pagado' => $d['monto_pagado'] ?? 0,
                'numero_comprobante' => $d['numero_comprobante'] ?? null,
                'cantidad_cuotas' => $d['cantidad_cuotas'] ?? null,
                'numero_cuota' => $d['numero_cuota'] ?? null,
                'monto_cuota' => $d['monto_cuota'] ?? null,
                'fecha_vencimiento' => $d['fecha_vencimiento'] ?? null,
                'saldo_pendiente' => $d['saldo_pendiente'] ?? 0,
                'creado_por' => $usuarioId,
            ];

            if ($hasBanco) {
                $params['banco'] = $d['banco'] ?? null;
            }

            $detailStmt->execute($params);
        }

        return $idPago;
    }

    /** Anula los cobros asociados a una venta sin borrar su auditoría. */
    public function anularPorVenta(int $idVenta): bool
    {
        $stmt = $this->db->prepare("UPDATE pago_cliente_cabecera SET estado = 'Anulado' WHERE id_venta = :id_venta AND estado <> 'Anulado'");
        return $stmt->execute(['id_venta' => $idVenta]);
    }
}
