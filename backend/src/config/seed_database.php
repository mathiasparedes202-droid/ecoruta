<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

try {
    $db = database();
    // Obtener IDs
    $comercioId = (int) $db->query("SELECT id_comercio FROM comercios LIMIT 1")->fetchColumn();
    $repartidorId = (int) $db->query("SELECT id_repartidor FROM repartidores LIMIT 1")->fetchColumn() ?: null;

    // 3. Crear Pedidos 
    $pedidosCount = (int) $db->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
