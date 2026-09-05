<?php

use Config\Database;

class ClienteSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $clientes = [
            [
                'nombre' => 'Carlos',
                'apellido' => 'González',
                'ci_ruc' => '80012345-6',
                'celular' => '0981 122334',
                'ciudad' => 'Asunción',
                'departamento' => 'Central',
                'tipo_cliente' => 'Persona',
                'estado' => 1,
                'creado_por' => 1,
            ],
            [
                'nombre' => 'María',
                'apellido' => 'López',
                'ci_ruc' => '80023456-7',
                'celular' => '0982 233445',
                'ciudad' => 'Encarnación',
                'departamento' => 'Itapúa',
                'tipo_cliente' => 'Persona',
                'estado' => 1,
                'creado_por' => 1,
            ],
            [
                'nombre' => 'Eventos',
                'apellido' => 'Ñandutí',
                'ci_ruc' => '80034567-8',
                'celular' => '0983 344556',
                'ciudad' => 'Asunción',
                'departamento' => 'Central',
                'tipo_cliente' => 'Empresa',
                'estado' => 1,
                'creado_por' => 1,
            ],
            [
                'nombre' => 'Casa',
                'apellido' => 'Kaeté',
                'ci_ruc' => '80045678-9',
                'celular' => '0984 455667',
                'ciudad' => 'Villarrica',
                'departamento' => 'Guairá',
                'tipo_cliente' => 'Empresa',
                'estado' => 1,
                'creado_por' => 1,
            ],
            [
                'nombre' => 'Mercado',
                'apellido' => 'Paraguay',
                'ci_ruc' => '80056789-0',
                'celular' => '0985 566778',
                'ciudad' => 'Ciudad del Este',
                'departamento' => 'Alto Paraná',
                'tipo_cliente' => 'Empresa',
                'estado' => 1,
                'creado_por' => 1,
            ],
        ];

        $sql = "INSERT INTO cliente (nombre, apellido, ci_ruc, celular, ciudad, departamento, tipo_cliente, estado, creado_por) VALUES (:nombre, :apellido, :ci_ruc, :celular, :ciudad, :departamento, :tipo_cliente, :estado, :creado_por)";
        $stmt = $db->prepare($sql);

        foreach ($clientes as $cliente) {
            try {
                $stmt->execute($cliente);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Clientes paraguayos sembrados.\n";
    }
}
