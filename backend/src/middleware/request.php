<?php

declare(strict_types=1);

function requestBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $body = json_decode($raw, true);
    if (!is_array($body)) {
        sendJson(['message' => 'El cuerpo debe ser JSON válido'], 400);
    }

    return $body;
}

function requireFields(array $body, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($body[$field]) || trim((string) $body[$field]) === '') {
            sendJson(['message' => "Falta completar: {$field}"], 422);
        }
    }
}
