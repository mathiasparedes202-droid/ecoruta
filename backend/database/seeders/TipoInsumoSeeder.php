<?php

use Config\Database;

class TipoInsumoSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $tipos = [
            ['nombre_tipo' => 'Flor', 'descripcion' => 'Flores naturales para arreglos'],
            ['nombre_tipo' => 'Follaje', 'descripcion' => 'Hojas y trim para diseño floral'],
            ['nombre_tipo' => 'Envoltorio', 'descripcion' => 'Papel, celofán y materiales para presentación'],
            ['nombre_tipo' => 'Accesorio', 'descripcion' => 'Cintas, lazos y adornos decorativos'],
            ['nombre_tipo' => 'Químico', 'descripcion' => 'Nutrientes, conservantes y fertilizantes ligeros'],
            ['nombre_tipo' => 'Seco', 'descripcion' => 'Flores y ramas secas para diseños rústicos'],
        ];

        $sql = "INSERT INTO tipo_insumo (nombre_tipo, descripcion) VALUES (:nombre_tipo, :descripcion)";

        $stmt = $db->prepare($sql);

        foreach ($tipos as $tipo) {
            try {
                $stmt->execute($tipo);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Tipos de insumo sembrados.\n";
    }
}
