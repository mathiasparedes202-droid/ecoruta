<?php

use Config\Database;

class VariedadSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $variedades = [
            ['nombre_variedad' => 'Rosa Criolla', 'descripcion' => 'Variedad nacional de alta durabilidad'],
            ['nombre_variedad' => 'Tulipán Amarillo', 'descripcion' => 'Tulipán brillante para arreglos modernos'],
            ['nombre_variedad' => 'Lirio Blanco', 'descripcion' => 'Lirio clásico de alta elegancia'],
            ['nombre_variedad' => 'Girasol', 'descripcion' => 'Girasol luminoso ideal para detalles alegres'],
            ['nombre_variedad' => 'Orquídea Satin', 'descripcion' => 'Orquídea delicada para arreglos premium'],
        ];

        $sql = "INSERT INTO variedad (nombre_variedad, descripcion) VALUES (:nombre_variedad, :descripcion)";

        $stmt = $db->prepare($sql);

        foreach ($variedades as $var) {
            try {
                $stmt->execute($var);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Variedades sembradas.\n";
    }
}
