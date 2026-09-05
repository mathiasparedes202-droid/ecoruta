<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/turnos_service.php';

function currentTurno(object $claims): never
{
    sendJson(getTurnoActual((int) $claims->sub));
}

function startTurnController(object $claims): never
{
    sendJson(startTurno((int) $claims->sub));
}

function pauseTurnController(object $claims): never
{
    sendJson(pauseTurno((int) $claims->sub));
}

function resumeTurnController(object $claims): never
{
    sendJson(resumeTurno((int) $claims->sub));
}

function finishTurnController(object $claims): never
{
    sendJson(finishTurno((int) $claims->sub));
}