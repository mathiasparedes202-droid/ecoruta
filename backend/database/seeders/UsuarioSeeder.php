<?php

use Config\Database;

class UsuarioSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $stmt = $db->prepare("SELECT id_rol FROM rol WHERE nombre_rol = 'Administrador' LIMIT 1");
        $stmt->execute();
        $adminRol = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT id_rol FROM rol WHERE nombre_rol = 'Vendedor' LIMIT 1");
        $stmt->execute();
        $vendedorRol = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminRol) {
            echo "Error: Rol admin no existe.\n";
            return;
        }

        if (!$vendedorRol) {
            echo "Error: Rol vendedor no existe.\n";
            return;
        }

        $adminRolId = $adminRol['id_rol'];
        $vendedorRolId = $vendedorRol['id_rol'];

        $passwordPlano = "admin";
        $passwordHash = password_hash($passwordPlano, PASSWORD_BCRYPT);

        $passwordPlano = "admin";
        $passwordHash = password_hash($passwordPlano, PASSWORD_BCRYPT);

        $usuarios = [
            [
                'ci' => '1234567',
                'nombre' => 'Administrador',
                'apellido' => 'Sistema',
                'email' => 'admin@floracia.com',
                'celular' => '0991123456',
                'id_rol' => $adminRolId
            ],
            [
                'ci' => '929646',
                'nombre' => 'Mathias',
                'apellido' => 'Paredes',
                'email' => 'mp929646@gmail.com',
                'celular' => '0990000000',
                'id_rol' => $vendedorRolId
            ]
        ];

        $query = "INSERT INTO usuario 
                  (ci_usuario, nombre, apellido, email, celular, password, id_rol) 
                  VALUES 
                  (:ci, :nombre, :apellido, :email, :celular, :password, :id_rol)";

        foreach ($usuarios as $usuario) {
            $checkStmt = $db->prepare("SELECT id_usuario FROM usuario WHERE email = :email LIMIT 1");
            $checkStmt->execute(['email' => $usuario['email']]);

            if ($checkStmt->fetch()) {
                echo "Usuario {$usuario['nombre']} {$usuario['apellido']} ya existe: {$usuario['email']}\n";
                continue;
            }

            $stmt = $db->prepare($query);
            $stmt->execute([
                'ci' => $usuario['ci'],
                'nombre' => $usuario['nombre'],
                'apellido' => $usuario['apellido'],
                'email' => $usuario['email'],
                'celular' => $usuario['celular'],
                'password' => $passwordHash,
                'id_rol' => $usuario['id_rol']
            ]);

            echo "Usuario {$usuario['nombre']} {$usuario['apellido']} creado correctamente: {$usuario['email']}\n";
        }
    }
}
