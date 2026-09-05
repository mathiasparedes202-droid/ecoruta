<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function listRoutes(): array
{
    try {
        $statement = database()->query(
            'SELECT id_pedido AS id, detalle_paquete AS name, direccion_destino AS description,
                    CASE id_estado WHEN 4 THEN "dificil" WHEN 3 THEN "moderada" ELSE "facil" END AS difficulty,
                    COALESCE(distancia_km, 0) AS distanceKm
             FROM pedidos ORDER BY fecha_solicitud DESC LIMIT 20'
        );
        $routes = $statement->fetchAll();
        if ($routes) {
            return $routes;
        }
    } catch (PDOException) {
    }
}
