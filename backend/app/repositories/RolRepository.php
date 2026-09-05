<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\Rol;

class RolRepository
{
    private PDO $db;

    // Constructor
    // Inicializa la conexión a la base de datos
    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function findById(int $id): ?Rol
    {
        $query = "SELECT * FROM rol WHERE id_rol = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new Rol($data) : null;
    }

    public function all(): array
    {
        $query = "SELECT * FROM rol ORDER BY id_rol DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mapear cada fila a un objeto Rol
        $roles = array_map(fn($data) => new Rol($data), $rows);

        return $roles;
    }

    public function create(Rol $rol): int
    {
        $query = "INSERT INTO rol (nombre_rol, descripcion, permisos_json, rol_del_sistema, estado)
                  VALUES (:nombre, :descripcion, :permisos, :sistema, :estado)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'nombre' => $rol->nombre_rol,
            'descripcion' => $rol->descripcion,
            'permisos' => json_encode(['permisos' => $rol->permisos_json]),
            'sistema' => $rol->rol_del_sistema,
            'estado' => $rol->estado,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(Rol $rol): bool
    {
        $query = "UPDATE rol
                  SET nombre_rol = :nombre,
                      descripcion = :descripcion,
                      permisos_json = :permisos,
                      rol_del_sistema = :sistema,
                      estado = :estado
                  WHERE id_rol = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'nombre' => $rol->nombre_rol,
            'descripcion' => $rol->descripcion,
            'permisos' => json_encode(['permisos' => $rol->permisos_json]),
            'sistema' => $rol->rol_del_sistema,
            'estado' => $rol->estado,
            'id' => $rol->id_rol,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM rol WHERE id_rol = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
