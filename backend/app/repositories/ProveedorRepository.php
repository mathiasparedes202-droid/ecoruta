<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\Proveedor;

class ProveedorRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function findById(int $id): ?Proveedor
    {
        $query = "SELECT * FROM proveedor WHERE id_proveedor = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new Proveedor($data) : null;
    }

    public function all(): array
    {
        $query = "SELECT * FROM proveedor WHERE estado = 1 ORDER BY id_proveedor DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Proveedor($data), $rows);
    }

    public function create(Proveedor $proveedor): int
    {
        $query = "INSERT INTO proveedor (razon_social, ruc, direccion, telefono, celular, correo, estado, creado_por)
                  VALUES (:razon_social, :ruc, :direccion, :telefono, :celular, :correo, :estado, :creado_por)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'razon_social' => $proveedor->razon_social,
            'ruc' => $proveedor->ruc,
            'direccion' => $proveedor->direccion,
            'telefono' => $proveedor->telefono,
            'celular' => $proveedor->celular,
            'correo' => $proveedor->correo,
            'estado' => $proveedor->estado,
            'creado_por' => $proveedor->creado_por,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(Proveedor $proveedor): bool
    {
        $query = "UPDATE proveedor
                  SET razon_social = :razon_social,
                      ruc = :ruc,
                      direccion = :direccion,
                      telefono = :telefono,
                      celular = :celular,
                      correo = :correo,
                      estado = :estado,
                      creado_por = :creado_por
                  WHERE id_proveedor = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'razon_social' => $proveedor->razon_social,
            'ruc' => $proveedor->ruc,
            'direccion' => $proveedor->direccion,
            'telefono' => $proveedor->telefono,
            'celular' => $proveedor->celular,
            'correo' => $proveedor->correo,
            'estado' => $proveedor->estado,
            'creado_por' => $proveedor->creado_por,
            'id' => $proveedor->id_proveedor,
        ]);
    }

    public function delete(int $id): bool
    {
        $query = "UPDATE proveedor SET estado = 0 WHERE id_proveedor = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
