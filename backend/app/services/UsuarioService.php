<?php

namespace App\Services;

use App\Models\Usuario;
use App\Repositories\UsuarioRepository;
use App\Validators\UsuarioValidator;

class UsuarioService
{
    private UsuarioRepository $usuarioRepo;

    public function __construct()
    {
        $this->usuarioRepo = new UsuarioRepository();
    }

    /**
     * Crea un nuevo usuario con password hasheada
     *
     * @param array $data ['ci_usuario','nombre','apellido','email','celular','password','id_rol','estado','creado_por']
     * @return Usuario
     */
    public function crearUsuario(array $data): Usuario
    {
        // Validación de datos
        UsuarioValidator::validarCreacion($data);

        // Hash de password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        $usuario = new Usuario($data);
        $id = $this->usuarioRepo->create($usuario);
        $usuario->id_usuario = $id;

        // Traer usuario con rol
        return $this->obtenerUsuario($id);
    }

    /**
     * Actualiza un usuario existente
     *
     * @param int $id
     * @param array $data ['ci_usuario','nombre','apellido','email','celular','password','id_rol','estado','creado_por']
     * @return Usuario
     */
    public function actualizarUsuario(int $id, array $data): Usuario
    {
        $usuario = $this->usuarioRepo->findById($id);
        if (!$usuario) {
            throw new \Exception("Usuario no encontrado");
        }

        // Validación de datos
        UsuarioValidator::validarActualizacion($data);

        // Actualizar propiedades
        $usuario->ci_usuario = $data['ci_usuario'] ?? $usuario->ci_usuario;
        $usuario->nombre = $data['nombre'] ?? $usuario->nombre;
        $usuario->apellido = $data['apellido'] ?? $usuario->apellido;
        $usuario->email = $data['email'] ?? $usuario->email;
        $usuario->celular = $data['celular'] ?? $usuario->celular;
        $usuario->id_rol = $data['id_rol'] ?? $usuario->id_rol;
        $usuario->estado = $data['estado'] ?? $usuario->estado;
        $usuario->creado_por = $data['creado_por'] ?? $usuario->creado_por;

        if (!empty($data['password'])) {
            $usuario->password = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $this->usuarioRepo->update($usuario);

        // Traer usuario actualizado con rol
        return $this->obtenerUsuario($id);
    }

    /**
     * Elimina un usuario
     *
     * @param int $id
     * @return bool
     */
    public function eliminarUsuario(int $id): bool
    {
        return $this->usuarioRepo->delete($id);
    }

    /**
     * Obtiene un usuario por ID con rol incluido
     *
     * @param int $id
     * @return Usuario|null
     */
    public function obtenerUsuario(int $id): ?Usuario
    {
        return $this->usuarioRepo->findById($id);
    }

    /**
     * Obtiene todos los usuarios con su rol
     *
     * @return Usuario[]
     */
    public function listarUsuarios(): array
    {
        return $this->usuarioRepo->all();
    }
}