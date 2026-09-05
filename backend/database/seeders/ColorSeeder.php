<?php

use Config\Database;

class ColorSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $colores = [
            ['nombre_color' => 'Rojo', 'codigo_hex' => '#FF0000'],
            ['nombre_color' => 'Blanco', 'codigo_hex' => '#FFFFFF'],
            ['nombre_color' => 'Amarillo', 'codigo_hex' => '#FFFF00'],
            ['nombre_color' => 'Rosa', 'codigo_hex' => '#FFC0CB'],
            ['nombre_color' => 'Morado', 'codigo_hex' => '#800080'],
        ];

        $sql = "INSERT INTO color (nombre_color, codigo_hex) VALUES (:nombre_color, :codigo_hex)";

        $stmt = $db->prepare($sql);

        foreach ($colores as $col) {
            try {
                $stmt->execute($col);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Colores sembrados.\n";
    }
}
