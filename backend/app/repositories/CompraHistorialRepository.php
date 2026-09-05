<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\CompraHistorial;

class CompraHistorialRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function create(array $data): int
    {
        $query = "INSERT INTO compra_historial (
                    id_compra,
                    usuario_id,
                    campo_modificado,
                    valor_anterior,
                    valor_nuevo,
                    motivo,
                    crear_nota_credito
                  ) VALUES (
                    :id_compra,
                    :usuario_id,
                    :campo_modificado,
                    :valor_anterior,
                    :valor_nuevo,
                    :motivo,
                    :crear_nota_credito
                  )";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'id_compra' => $data['id_compra'],
            'usuario_id' => $data['usuario_id'] ?? null,
            'campo_modificado' => $data['campo_modificado'],
            'valor_anterior' => $data['valor_anterior'] ?? null,
            'valor_nuevo' => $data['valor_nuevo'] ?? null,
            'motivo' => $data['motivo'] ?? null,
            'crear_nota_credito' => $data['crear_nota_credito'] ?? false,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getByCompra(int $idCompra): array
    {
        $query = "SELECT ch.*, 
                  CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario
                  FROM compra_historial ch
                  LEFT JOIN usuario u ON ch.usuario_id = u.id_usuario
                  WHERE ch.id_compra = :id_compra
                  ORDER BY ch.fecha_cambio DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['id_compra' => $idCompra]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new CompraHistorial($data), $rows);
    }

    public function deleteByCompra(int $idCompra): bool
    {
        $query = "DELETE FROM compra_historial WHERE id_compra = :id_compra";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id_compra' => $idCompra]);
    }

    public function registrarCambio(int $idCompra, string $campo, int $usuarioId, ?string $valorAnterior = null, ?string $valorNuevo = null, ?string $motivo = null, ?bool $crearNotaCredito = null): int
    {
        $query = "INSERT INTO compra_historial (
                    id_compra,
                    usuario_id,
                    campo_modificado,
                    valor_anterior,
                    valor_nuevo,
                    motivo,
                    crear_nota_credito
                  ) VALUES (
                    :id_compra,
                    :usuario_id,
                    :campo_modificado,
                    :valor_anterior,
                    :valor_nuevo,
                    :motivo,
                    :crear_nota_credito
                  )";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'id_compra' => $idCompra,
            'usuario_id' => $usuarioId,
            'campo_modificado' => $campo,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo' => $valorNuevo,
            'motivo' => $motivo,
            'crear_nota_credito' => $crearNotaCredito,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
