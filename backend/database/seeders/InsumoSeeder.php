<?php

use Config\Database;

class InsumoSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $insumos = [
            [
                'nombre_insumo' => 'Rosa Criolla',
                'descripcion' => 'Rosa nacional de alta calidad para ramos formales',
                'id_tipo_insumo' => 1,
                'id_variedad' => 1,
                'id_color' => 1,
                'id_categoria' => 1,
                'id_unidad' => 1,
                'stock' => 80,
            ],
            [
                'nombre_insumo' => 'Tulipán Amarillo',
                'descripcion' => 'Tulipán brillante para arreglos primaverales',
                'id_tipo_insumo' => 1,
                'id_variedad' => 2,
                'id_color' => 3,
                'id_categoria' => 2,
                'id_unidad' => 1,
                'stock' => 30,
            ],
            [
                'nombre_insumo' => 'Cinta Satinada Roja',
                'descripcion' => 'Cinta satinada para lazos y presentación',
                'id_tipo_insumo' => 4,
                'id_categoria' => 5,
                'id_unidad' => 1,
                'stock' => 120,
            ],
            [
                'nombre_insumo' => 'Lirio Blanco',
                'descripcion' => 'Lirio elegante para arreglos grandes',
                'id_tipo_insumo' => 1,
                'id_variedad' => 3,
                'id_color' => 2,
                'id_categoria' => 1,
                'id_unidad' => 1,
                'stock' => 45,
            ],
            [
                'nombre_insumo' => 'Papel Kraft',
                'descripcion' => 'Papel kraft para envolver ramos y centros de mesa',
                'id_tipo_insumo' => 3,
                'id_categoria' => 4,
                'id_unidad' => 4,
                'stock' => 200,
            ],
            [
                'nombre_insumo' => 'Follaje Eucalipto',
                'descripcion' => 'Hojas de eucalipto para relleno y aroma fresco',
                'id_tipo_insumo' => 2,
                'id_categoria' => 3,
                'id_unidad' => 1,
                'stock' => 70,
            ],
        ];

        $sql = "INSERT INTO insumo (nombre_insumo, descripcion, id_tipo_insumo, id_variedad, id_color, id_categoria, id_unidad, stock) VALUES (:nombre_insumo, :descripcion, :id_tipo_insumo, :id_variedad, :id_color, :id_categoria, :id_unidad, :stock)";

        $stmt = $db->prepare($sql);

        foreach ($insumos as $ins) {
            try {
                $stmt->execute($ins);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Insumos sembrados.\n";
    }
}
