<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/metrics_service.php';

function getDailyMetrics(): never
{
    $desde = $_GET['desde'] ?? null;
    $hasta = $_GET['hasta'] ?? null;
    sendJson(getMetrics($desde, $hasta));
}
