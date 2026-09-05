<?php

use Config\Database;

class UnidadSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $unidades = [
            ['nombre_unidad' => 'Unidad', 'simbolo' => 'u'],
            ['nombre_unidad' => 'Docena', 'simbolo' => 'dz'],
            ['nombre_unidad' => 'Kilogramo', 'simbolo' => 'kg'],
            ['nombre_unidad' => 'Metro', 'simbolo' => 'm'],
            ['nombre_unidad' => 'Paquete', 'simbolo' => 'paq'],
        ];

        $sql = "INSERT INTO unidad (nombre_unidad, simbolo) VALUES (:nombre_unidad, :simbolo)";

        $stmt = $db->prepare($sql);

        foreach ($unidades as $uni) {
            try {
                $stmt->execute($uni);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Unidades sembradas.\n";
    }
}
