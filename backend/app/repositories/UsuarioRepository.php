<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\Usuario;
use App\Models\Rol;

class UsuarioRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    /**
     * Obtiene un usuario por su ID (con rol incluido)
     */
    public function findById(int $id): ?Usuario
    {
        $query = "SELECT u.*, r.id_rol AS rol_id, r.nombre_rol, r.permisos_json, r.descripcion AS rol_descripcion, r.rol_del_sistema, r.estado AS rol_estado, r.fecha_creacion AS rol_fecha_creacion
                  FROM usuario u
                  JOIN rol r ON u.id_rol = r.id_rol
                  WHERE u.id_usuario = :id
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) return null;

        // Crear usuario
        $usuario = new Usuario($data);

        // Crear rol
        $rolData = [
            'id_rol' => $data['rol_id'],
            'nombre_rol' => $data['nombre_rol'],
            'descripcion' => $data['rol_descripcion'],
            'permisos_json' => $data['permisos_json'],
            'rol_del_sistema' => $data['rol_del_sistema'],
            'estado' => $data['rol_estado'],
            'fecha_creacion' => $data['rol_fecha_creacion']
        ];

        $usuario->rol = new Rol($rolData);

        return $usuario;
    }

    /**
     * Obtiene un usuario por email (para login) con rol incluido
     */
    public function findByEmail(string $email): ?Usuario
    {
        $query = "SELECT u.*, r.id_rol AS rol_id, r.nombre_rol, r.permisos_json, r.descripcion AS rol_descripcion, r.rol_del_sistema, r.estado AS rol_estado, r.fecha_creacion AS rol_fecha_creacion
                  FROM usuario u
                  JOIN rol r ON u.id_rol = r.id_rol
                  WHERE u.email = :email
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) return null;

        $usuario = new Usuario($data);

        $rolData = [
            'id_rol' => $data['rol_id'],
            'nombre_rol' => $data['nombre_rol'],
            'descripcion' => $data['rol_descripcion'],
            'permisos_json' => $data['permisos_json'],
            'rol_del_sistema' => $data['rol_del_sistema'],
            'estado' => $data['rol_estado'],
            'fecha_creacion' => $data['rol_fecha_creacion']
        ];

        $usuario->rol = new Rol($rolData);

        return $usuario;
    }

    /**
     * Crea un nuevo usuario
     */
    public function create(Usuario $usuario): int
    {
        $query = "INSERT INTO usuario (ci_usuario, nombre, apellido, email, celular, password, id_rol, estado, creado_por)
                  VALUES (:ci, :nombre, :apellido, :email, :celular, :password, :rol_id, :estado, :creado_por)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'ci' => $usuario->ci_usuario,
            'nombre' => $usuario->nombre,
            'apellido' => $usuario->apellido,
            'email' => $usuario->email,
            'celular' => $usuario->celular,
            'password' => $usuario->password, // ya hasheada
            'rol_id' => $usuario->id_rol,
            'estado' => $usuario->estado,
            'creado_por' => $usuario->creado_por
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza un usuario existente
     */
    public function update(Usuario $usuario): bool
    {
        $query = "UPDATE usuario
                  SET ci_usuario = :ci,
                      nombre = :nombre,
                      apellido = :apellido,
                      email = :email,
                      celular = :celular,
                      password = :password,
                      id_rol = :rol_id,
                      estado = :estado,
                      creado_por = :creado_por
                  WHERE id_usuario = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'ci' => $usuario->ci_usuario,
            'nombre' => $usuario->nombre,
            'apellido' => $usuario->apellido,
            'email' => $usuario->email,
            'celular' => $usuario->celular,
            'password' => $usuario->password,
            'rol_id' => $usuario->id_rol,
            'estado' => $usuario->estado,
            'creado_por' => $usuario->creado_por,
            'id' => $usuario->id_usuario
        ]);
    }

    /**
     * Elimina un usuario por ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM usuario WHERE id_usuario = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Obtiene todos los usuarios con su rol
     */
    public function all(): array
    {
        $query = "SELECT u.*, r.id_rol AS rol_id, r.nombre_rol, r.permisos_json, r.descripcion AS rol_descripcion, r.rol_del_sistema, r.estado AS rol_estado, r.fecha_creacion AS rol_fecha_creacion
                  FROM usuario u
                  JOIN rol r ON u.id_rol = r.id_rol
                  ORDER BY u.id_usuario DESC";

        $stmt = $this->db->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $usuarios = [];
        foreach ($rows as $data) {
            $usuario = new Usuario($data);

            $rolData = [
                'id_rol' => $data['rol_id'],
                'nombre_rol' => $data['nombre_rol'],
                'descripcion' => $data['rol_descripcion'],
                'permisos_json' => $data['permisos_json'],
                'rol_del_sistema' => $data['rol_del_sistema'],
                'estado' => $data['rol_estado'],
                'fecha_creacion' => $data['rol_fecha_creacion']
            ];

            $usuario->rol = new Rol($rolData);

            $usuarios[] = $usuario;
        }

        return $usuarios;
    }
}