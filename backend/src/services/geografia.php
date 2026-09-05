<?php

declare(strict_types=1);

/**
 * Límites geográficos: EcoRuta opera únicamente dentro de Paraguay.
 */
const PARAGUAY_MIN_LAT = -27.60;
const PARAGUAY_MAX_LAT = -19.20;
const PARAGUAY_MIN_LNG = -62.70;
const PARAGUAY_MAX_LNG = -54.20;

function coordsInParaguay(?float $lat, ?float $lng): bool
{
    if ($lat === null || $lng === null) {
        return false;
    }
    return $lat >= PARAGUAY_MIN_LAT && $lat <= PARAGUAY_MAX_LAT
        && $lng >= PARAGUAY_MIN_LNG && $lng <= PARAGUAY_MAX_LNG;
}

/**
 * Extrae (lat, lng) de un texto tipo "dirección (lat, lng) -> puede contener
 * decimales con coma o punto, grados negativos".
 */
function extractCoords(string $text): ?array
{
    if (!preg_match('/(-?\d+(?:[.,]\d+)?)\s*[,;]\s*(-?\d+(?:[.,]\d+)?)/u', $text, $matches)) {
        return null;
    }
    $lat = (float) str_replace(',', '.', $matches[1]);
    $lng = (float) str_replace(',', '.', $matches[2]);

    // PostgreSQL/GeoJSON corrientes: lat va primero en el texto "(-23.4..., -57.4...)"
    if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
        return ['lat' => $lat, 'lng' => $lng];
    }
    return null;
}