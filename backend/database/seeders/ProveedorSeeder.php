<?php

use Config\Database;

class ProveedorSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $proveedores = [
            [
                'razon_social' => 'Florería Asunción',
                'ruc' => '80012345-1',
                'direccion' => 'Av. España 847, Asunción',
                'telefono' => '021 444 555',
                'correo' => 'contacto@floreriayasuncion.com',
            ],
            [
                'razon_social' => 'Flores del Guairá',
                'ruc' => '80023456-2',
                'direccion' => 'Av. Gral. Díaz 5200, Villarrica',
                'telefono' => '041 234 567',
                'correo' => 'ventas@floresguaira.com',
            ],
            [
                'razon_social' => 'Ramos y Eventos PY',
                'ruc' => '80034567-3',
                'direccion' => 'Ruta II Km 12, Mariano Roque Alonso',
                'telefono' => '021 777 888',
                'correo' => 'pedidos@ramosypy.com',
            ],
            [
                'razon_social' => 'Jardín del Lago',
                'ruc' => '80045678-4',
                'direccion' => 'Av. Costanera 230, Encarnación',
                'telefono' => '071 223 344',
                'correo' => 'info@jardindellago.com',
            ],
            [
                'razon_social' => 'Distribuidora de Flores S.A.',
                'ruc' => '80056789-5',
                'direccion' => 'Zona Mcal. Estigarribia 1234, Ciudad del Este',
                'telefono' => '061 332 211',
                'correo' => 'ventas@distribuidoraflores.com',
            ],
        ];

        $sql = "INSERT INTO proveedor (razon_social, ruc, direccion, telefono, correo) VALUES (:razon_social, :ruc, :direccion, :telefono, :correo)";

        $stmt = $db->prepare($sql);

        foreach ($proveedores as $prov) {
            try {
                $stmt->execute($prov);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Proveedores sembrados.\n";
    }
}
