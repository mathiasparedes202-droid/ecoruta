<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const METRICS_DAYS = 6; // 6 días hacia atrás + hoy = ventana de 7 días

/**
 * Métricas del panel de administración: devuelve la actividad diaria de los
 * últimos 7 días y un resumen que combina la fotografía actual del sistema
 * (pedidos en curso, plata por cobrar), la recaudación por método de pago y
 * el desempeño de los repartidores en el período.
 */
function getMetrics(): array
{
    $db = database();

    // Histórico persistido en métricas diarias (si existe)
    $statement = $db->query(
        'SELECT fecha_reporte, total_pedidos, entregados, co2_total_ahorrado_kg, km_recorridos_sin_emision
         FROM metricas_diarias ORDER BY fecha_reporte DESC LIMIT 30'
    );
    $stored = [];
    foreach ($statement->fetchAll() as $row) {
        $stored[$row['fecha_reporte']] = $row;
    }

    // Estado de los pedidos por día (últimos 7 días incluida hoy).
    // Un pedido entregado cuenta en el día de su ENTREGA; el resto, en el día de solicitud.
    // Así la fila del día queda coherente: total_pedidos >= entregados y el cumplimiento tiene sentido.
    $estados = $db->query(
        'SELECT DATE(CASE WHEN id_estado = 4 THEN fecha_entrega ELSE fecha_solicitud END) AS dia,
                id_estado, COUNT(*) AS c
         FROM pedidos
         WHERE (id_estado = 4 AND fecha_entrega >= DATE_SUB(CURDATE(), INTERVAL ' . METRICS_DAYS . ' DAY))
            OR (id_estado <> 4 AND fecha_solicitud >= DATE_SUB(CURDATE(), INTERVAL ' . METRICS_DAYS . ' DAY))
         GROUP BY dia, id_estado'
    )->fetchAll();

    $byDay = [];
    foreach ($estados as $row) {
        $dia = $row['dia'];
        $count = (int) $row['c'];
        if (!isset($byDay[$dia])) {
            $byDay[$dia] = emptyDayRow($dia);
        }
        $byDay[$dia]['total_pedidos'] += $count;
        $byDay[$dia]['pendientes'] += (int) $row['id_estado'] === 1 ? $count : 0;
        $byDay[$dia]['asignados'] += (int) $row['id_estado'] === 2 ? $count : 0;
        $byDay[$dia]['en_camino'] += (int) $row['id_estado'] === 3 ? $count : 0;
        $byDay[$dia]['entregados'] += (int) $row['id_estado'] === 4 ? $count : 0;
        $byDay[$dia]['cancelados'] += (int) $row['id_estado'] === 5 ? $count : 0;
    }

    // Impacto de los entregados (según fecha de entrega): co2, km, ingresos,
    // cuánto se cobró y cuánto queda pendiente, más el tiempo promedio de entrega.
    $entregados = $db->query(
        'SELECT DATE(fecha_entrega) AS dia,
                COUNT(*) AS entregados,
                COALESCE(SUM(co2_ahorrado_kg), 0) AS co2_ahorrado_kg,
                COALESCE(SUM(distancia_km), 0) AS km_recorridos_sin_emision,
                COALESCE(SUM(tarifa_ecologica), 0) AS ingresos_gs,
                COALESCE(SUM(CASE WHEN pagado = 1 THEN 1 ELSE 0 END), 0) AS pagados,
                COALESCE(SUM(CASE WHEN pagado = 1 THEN tarifa_ecologica ELSE 0 END), 0) AS ingresos_cobrados,
                COALESCE(SUM(CASE WHEN pagado = 0 THEN tarifa_ecologica ELSE 0 END), 0) AS ingresos_pendientes,
                COALESCE(ROUND(AVG(TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_entrega)), 1), 0) AS tiempo_promedio_entrega_min
         FROM pedidos
         WHERE id_estado = 4 AND fecha_entrega IS NOT NULL
           AND fecha_entrega >= DATE_SUB(CURDATE(), INTERVAL ' . METRICS_DAYS . ' DAY)
         GROUP BY DATE(fecha_entrega)'
    )->fetchAll();

    foreach ($entregados as $row) {
        $dia = $row['dia'];
        if (!isset($byDay[$dia])) {
            $byDay[$dia] = emptyDayRow($dia);
        }
        $byDay[$dia]['entregados'] = (int) $row['entregados'];
        $byDay[$dia]['co2_total_ahorrado_kg'] = round((float) $row['co2_ahorrado_kg'], 4);
        $byDay[$dia]['km_recorridos_sin_emision'] = round((float) $row['km_recorridos_sin_emision'], 3);
        $byDay[$dia]['ingresos_gs'] = round((float) $row['ingresos_gs'], 2);
        $byDay[$dia]['pagados'] = (int) $row['pagados'];
        $byDay[$dia]['ingresos_cobrados'] = round((float) $row['ingresos_cobrados'], 2);
        $byDay[$dia]['ingresos_pendientes'] = round((float) $row['ingresos_pendientes'], 2);
        $byDay[$dia]['tiempo_promedio_entrega_min'] = (float) $row['tiempo_promedio_entrega_min'];
    }

    // Normalizar filas: la actividad viva de la ventana manda; lo persistido en
    // metricas_diarias (si existe, por ejemplo cargas históricas) solo completa
    // días anteriores que ya no tienen movimiento en vivo.
    $merged = [];
    foreach ($byDay as $dia => $liveRow) {
        $merged[$dia] = finalizeMetricRow($liveRow, $liveRow, false);
    }
    foreach ($stored as $dia => $storedRow) {
        if (!isset($merged[$dia])) {
            $merged[$dia] = finalizeMetricRow($storedRow, emptyDayRow($dia), true);
        }
    }

    uksort($merged, static fn (string $a, string $b): int => strcmp($b, $a));

    $diario = array_values(array_slice($merged, 0, 30));

    return [
        'diario' => $diario,
        'resumen' => buildResumen($diario),
    ];
}

function emptyDayRow(string $fecha): array
{
    return [
        'fecha_reporte' => $fecha,
        'total_pedidos' => 0,
        'pendientes' => 0,
        'asignados' => 0,
        'en_camino' => 0,
        'entregados' => 0,
        'cancelados' => 0,
        'co2_total_ahorrado_kg' => 0.0,
        'km_recorridos_sin_emision' => 0.0,
        'ingresos_gs' => 0.0,
        'pagados' => 0,
        'ingresos_cobrados' => 0.0,
        'ingresos_pendientes' => 0.0,
        'tiempo_promedio_entrega_min' => 0.0,
    ];
}

/**
 * Completa una fila de métricas con todos los campos para el panel
 * y derivados de lectura (en_curso, tasa de entrega, promedios).
 */
function finalizeMetricRow(array $row, array $live, bool $fromStored): array
{
    $total = (int) ($row['total_pedidos'] ?? $live['total_pedidos'] ?? 0);
    $entregados = (int) ($row['entregados'] ?? $live['entregados'] ?? 0);
    $pendientes = $fromStored ? (int) ($live['pendientes'] ?? 0) : (int) ($row['pendientes'] ?? 0);
    $asignados = $fromStored ? (int) ($live['asignados'] ?? 0) : (int) ($row['asignados'] ?? 0);
    $enCamino = $fromStored ? (int) ($live['en_camino'] ?? 0) : (int) ($row['en_camino'] ?? 0);
    $cancelados = $fromStored ? (int) ($live['cancelados'] ?? 0) : (int) ($row['cancelados'] ?? 0);
    $co2 = (float) ($row['co2_total_ahorrado_kg'] ?? $live['co2_total_ahorrado_kg'] ?? 0);
    $km = (float) ($row['km_recorridos_sin_emision'] ?? $live['km_recorridos_sin_emision'] ?? 0);
    $ingresos = $fromStored ? (float) ($live['ingresos_gs'] ?? 0) : (float) ($row['ingresos_gs'] ?? $live['ingresos_gs'] ?? 0);

    return [
        'fecha_reporte' => $row['fecha_reporte'],
        'total_pedidos' => $total,
        'pendientes' => $pendientes,
        'asignados' => $asignados,
        'en_camino' => $enCamino,
        'entregados' => $entregados,
        'cancelados' => $cancelados,
        'en_curso' => $asignados + $enCamino,
        'tasa_entregas' => $total > 0 ? round($entregados / $total * 100, 1) : 0.0,
        'promedio_km' => $entregados > 0 ? round($km / $entregados, 2) : 0.0,
        'co2_total_ahorrado_kg' => round($co2, 4),
        'km_recorridos_sin_emision' => round($km, 3),
        'ingresos_gs' => round($ingresos, 2),
        'pagados' => (int) ($live['pagados'] ?? 0),
        'ingresos_cobrados' => round((float) ($live['ingresos_cobrados'] ?? 0), 2),
        'ingresos_pendientes' => round((float) ($live['ingresos_pendientes'] ?? 0), 2),
        'tiempo_promedio_entrega_min' => (float) ($live['tiempo_promedio_entrega_min'] ?? 0),
    ];
}

/**
 * Resumen consolidado del panel: totales de la ventana, fotografía actual de
 * estados y cobros, recaudación por método de pago y ranking de repartidores.
 */
function buildResumen(array $diario): array
{
    $sum = static function (string $key) use ($diario): float {
        $acc = 0.0;
        foreach ($diario as $row) {
            $acc += (float) ($row[$key] ?? 0);
        }
        return $acc;
    };

    $db = database();

    // Fotografía actual: qué está pasando ahora mismo
    $snapshot = $db->query(
        "SELECT
            COALESCE(SUM(CASE WHEN id_estado = 1 THEN 1 ELSE 0 END), 0) AS pendientes,
            COALESCE(SUM(CASE WHEN id_estado IN (2, 3) THEN 1 ELSE 0 END), 0) AS en_curso,
            COALESCE(SUM(CASE WHEN id_estado IN (1, 2, 3) AND pagado = 0 THEN 1 ELSE 0 END), 0) AS por_cobrar,
            COALESCE(SUM(CASE WHEN id_estado IN (1, 2, 3) AND pagado = 0 THEN tarifa_ecologica ELSE 0 END), 0) AS por_cobrar_monto
         FROM pedidos"
    )->fetch();

    // Tiempo promedio general de la ventana (entregas de los últimos 7 días)
    $tiempo = $db->query(
        'SELECT COALESCE(ROUND(AVG(TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_entrega)), 0), 0) AS min
         FROM pedidos
         WHERE id_estado = 4 AND fecha_entrega IS NOT NULL
           AND fecha_entrega >= DATE_SUB(CURDATE(), INTERVAL ' . METRICS_DAYS . ' DAY)'
    )->fetch();

    // Recaudación según el método de pago (entregas de la ventana)
    $porPago = $db->query(
        "SELECT CASE
                    WHEN metodo_pago = 'transferencia' THEN 'transferencia'
                    WHEN metodo_pago = 'mixto' THEN 'mixto'
                    WHEN metodo_pago = 'efectivo' THEN 'efectivo'
                    ELSE 'sin_especificar'
                END AS metodo,
                COUNT(*) AS entregas,
                COALESCE(SUM(tarifa_ecologica), 0) AS monto
         FROM pedidos
         WHERE id_estado = 4 AND fecha_entrega >= DATE_SUB(CURDATE(), INTERVAL ' . METRICS_DAYS . ' DAY)
         GROUP BY metodo"
    )->fetchAll();

    $resultadoPago = [];
    foreach (['efectivo', 'transferencia', 'mixto', 'sin_especificar'] as $metodo) {
        $resultadoPago[$metodo] = ['entregas' => 0, 'monto' => 0.0];
    }
    foreach ($porPago as $row) {
        $metodo = $row['metodo'];
        $resultadoPago[$metodo] = [
            'entregas' => (int) $row['entregas'],
            'monto' => round((float) $row['monto'], 2),
        ];
    }

    // Ranking: los repartidores con más entregas en la ventana
    $ranking = $db->query(
        'SELECT u.nombre_completo,
                COUNT(p.id_pedido) AS entregas,
                COALESCE(SUM(p.distancia_km), 0) AS km,
                COALESCE(SUM(p.co2_ahorrado_kg), 0) AS co2
         FROM pedidos p
         JOIN repartidores r ON r.id_repartidor = p.id_repartidor
         JOIN usuarios u ON u.id_usuario = r.id_usuario
         WHERE p.id_estado = 4 AND p.fecha_entrega >= DATE_SUB(CURDATE(), INTERVAL ' . METRICS_DAYS . ' DAY)
         GROUP BY r.id_repartidor, u.nombre_completo
         ORDER BY entregas DESC, km DESC
         LIMIT 5'
    )->fetchAll();

    $rankingFinal = [];
    foreach ($ranking as $index => $row) {
        $rankingFinal[] = [
            'posicion' => $index + 1,
            'nombre_completo' => $row['nombre_completo'],
            'entregas' => (int) $row['entregas'],
            'km' => round((float) $row['km'], 2),
            'co2' => round((float) $row['co2'], 3),
        ];
    }

    $total = $sum('total_pedidos');
    $entregados = $sum('entregados');

    return [
        'total_pedidos' => (int) $total,
        'entregados' => (int) $entregados,
        'cancelados' => (int) $sum('cancelados'),
        'pendientes_snapshot' => (int) ($snapshot['pendientes'] ?? 0),
        'en_curso_snapshot' => (int) ($snapshot['en_curso'] ?? 0),
        'por_cobrar' => (int) ($snapshot['por_cobrar'] ?? 0),
        'por_cobrar_monto' => round((float) ($snapshot['por_cobrar_monto'] ?? 0), 2),
        'co2_total_ahorrado_kg' => round($sum('co2_total_ahorrado_kg'), 4),
        'km_recorridos_sin_emision' => round($sum('km_recorridos_sin_emision'), 3),
        'ingresos_gs' => round($sum('ingresos_gs'), 2),
        'ingresos_cobrados' => round($sum('ingresos_cobrados'), 2),
        'ingresos_pendientes' => round($sum('ingresos_pendientes'), 2),
        'tasa_entregas' => $total > 0 ? round($entregados / $total * 100, 1) : 0.0,
        'tiempo_promedio_entrega_min' => (int) ($tiempo['min'] ?? 0),
        'por_pago' => $resultadoPago,
        'ranking_repartidores' => $rankingFinal,
    ];
}