<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\PagoProveedorDetalle;

class PagoProveedorDetalleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function create(array $data): int
    {
        $query = "INSERT INTO pago_proveedor_detalle (
                    id_pago_proveedor,
                    metodo_pago,
                    numero_comprobante,
                    banco,
                    fecha_vencimiento,
                    estado_pago,
                    observaciones
                  ) VALUES (
                    :id_pago_proveedor,
                    :metodo_pago,
                    :numero_comprobante,
                    :banco,
                    :fecha_vencimiento,
                    :estado_pago,
                    :observaciones
                  )";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'id_pago_proveedor' => $data['id_pago_proveedor'],
            'metodo_pago' => $data['metodo_pago'] ?? 'Efectivo',
            'numero_comprobante' => $data['numero_comprobante'] ?? null,
            'banco' => $data['banco'] ?? null,
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'estado_pago' => $data['estado_pago'] ?? 'Pendiente',
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findByPago(int $idPago): ?PagoProveedorDetalle
    {
        $query = "SELECT * FROM pago_proveedor_detalle WHERE id_pago_proveedor = :id_pago";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['id_pago' => $idPago]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new PagoProveedorDetalle($data) : null;
    }

    public function findById(int $id): ?PagoProveedorDetalle
    {
        $query = "SELECT * FROM pago_proveedor_detalle WHERE id_detalle = :id";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new PagoProveedorDetalle($data) : null;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = ['id' => $id];

        foreach (['numero_comprobante', 'banco', 'fecha_vencimiento', 'estado_pago', 'observaciones'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $values[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $query = "UPDATE pago_proveedor_detalle SET " . implode(', ', $fields) . " WHERE id_detalle = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $query = "DELETE FROM pago_proveedor_detalle WHERE id_detalle = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
}
