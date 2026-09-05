<?php

use Config\Database;

class CategoriaSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $categorias = [
            ['nombre_categoria' => 'Rosas paraguayas', 'descripcion' => 'Rosas nacionales para ramos y eventos'],
            ['nombre_categoria' => 'Ramos y arreglos', 'descripcion' => 'Arreglos florales para bodas, cumpleaños y aniversarios'],
            ['nombre_categoria' => 'Centros de mesa', 'descripcion' => 'Centros decorativos para mesas de eventos y reuniones'],
            ['nombre_categoria' => 'Envoltorios y embalajes', 'descripcion' => 'Papel, celofán y materiales para presentación'],
            ['nombre_categoria' => 'Accesorios y complementos', 'descripcion' => 'Cintas, lazos, tarjetas y adornos para regalos'],
        ];

        $sql = "INSERT INTO categoria (nombre_categoria, descripcion) VALUES (:nombre_categoria, :descripcion)";

        $stmt = $db->prepare($sql);

        foreach ($categorias as $cat) {
            try {
                $stmt->execute($cat);
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Categorías sembradas.\n";
    }
}
