<?php

use Config\Database;

class RolSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        try {
            $sqlAdmin = "INSERT INTO rol (nombre_rol, descripcion, permisos_json, rol_del_sistema)
                         VALUES (:nombre, :descripcion, :permisos, :sistema)";

            $stmt = $db->prepare($sqlAdmin);

            $stmt->execute([
                'nombre' => 'Administrador',
                'descripcion' => 'Administrador del sistema',
                'permisos' => json_encode([
                    'permisos' => ['admin.*']
                ]),
                'sistema' => 1
            ]);

            $stmt->execute([
                'nombre' => 'Vendedor',
                'descripcion' => 'Usuario de ventas',
                'permisos' => json_encode([
                    'permisos' => [
                        'ventas.registrar',
                        'ventas.cobrar',
                        'clientes.ver',
                        'caja.apertura',
                        'caja.cierre'
                    ]
                ]),
                'sistema' => 0
            ]);

            echo "Roles creados correctamente.\n";

        } catch (Exception $e) {
            echo "Los roles ya existen o ocurrió un error.\n";
        }
    }
}