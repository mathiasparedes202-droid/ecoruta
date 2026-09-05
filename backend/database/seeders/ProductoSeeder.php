<?php

use Config\Database;

class ProductoSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $productos = [
            [
                'nombre_producto' => 'Ramo de Amor',
                'tipo_producto' => 'Ramo',
                'descripcion' => 'Ramo romántico con rosas criollas y follaje verde',
                    'costo_produccion' => 135000.00,
                    'precio_base' => 240000.00,
                'estado' => 1,
                'es_personalizable' => 1,
                'creado_por' => 1,
            ],
            [
                'nombre_producto' => 'Corona de Condolencias',
                'tipo_producto' => 'Corona',
                'descripcion' => 'Corona formal para homenajes y ceremonias',
                'costo_produccion' => 180000.00,
                'precio_base' => 320000.00,
                    'costo_produccion' => 190000.00,
                    'precio_base' => 350000.00,
                'estado' => 1,
                'es_personalizable' => 0,
                'creado_por' => 1,
            ],
            [
                'nombre_producto' => 'Detalle Paraguayo',
                'tipo_producto' => 'Detalle',
                'descripcion' => 'Arreglo pequeño con flores nacionales y artesanía local',
                    'costo_produccion' => 85000.00,
                    'precio_base' => 150000.00,
                'estado' => 1,
                'es_personalizable' => 1,
                'creado_por' => 1,
            ],
            [
                'nombre_producto' => 'Ramo Primavera',
                'tipo_producto' => 'Ramo',
                'descripcion' => 'Ramo fresco con tonos amarillos, rosas y verdes',
                    'costo_produccion' => 100000.00,
                    'precio_base' => 200000.00,
                'estado' => 1,
                'es_personalizable' => 1,
                'creado_por' => 1,
            ],
            [
                'nombre_producto' => 'Centro de Mesa Tradicional',
                'tipo_producto' => 'Detalle',
                'descripcion' => 'Centro de mesa con flores paraguayas y follaje elegante',
                    'costo_produccion' => 90000.00,
                    'precio_base' => 180000.00,
                'estado' => 1,
                'es_personalizable' => 1,
                'creado_por' => 1,
            ],
        ];

        $sql = "INSERT INTO producto (nombre_producto, tipo_producto, descripcion, costo_produccion, precio_base, estado, es_personalizable, creado_por) VALUES (:nombre_producto, :tipo_producto, :descripcion, :costo_produccion, :precio_base, :estado, :es_personalizable, :creado_por)";
        $stmt = $db->prepare($sql);

        foreach ($productos as $producto) {
            try {
                $stmt->execute($producto);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Productos sembrados.\n";
    }
}
