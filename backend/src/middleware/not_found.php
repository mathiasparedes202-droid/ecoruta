<?php

declare(strict_types=1);

function sendNotFound(): never
{
    sendJson(['message' => 'Recurso no encontrado'], 404);
}
