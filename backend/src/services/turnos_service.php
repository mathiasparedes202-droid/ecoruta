<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/request.php';

const TURNO_ACTIVO = 'activo';
const TURNO_PAUSADO = 'pausado';
const TURNO_FINALIZADO = 'finalizado';

/**
 * Devuelve el registro de repartidor asociado al usuario autenticado.
 * Solo los repartidores (rol 2) pueden gestionar su turno.
 */
function repartidorDelUsuario(int $userId): array
{
    $stmt = database()->prepare(
        'SELECT id_repartidor, id_usuario, disponible, tipo_vehiculo FROM repartidores WHERE id_usuario = :user LIMIT 1'
    );
    $stmt->execute(['user' => $userId]);
    $rep = $stmt->fetch();
    if (!$rep) {
        sendJson(['message' => 'Solo los repartidores pueden gestionar su turno'], 403);
    }
    return $rep;
}

function turnoAbierto(int $repartidorId): ?array
{
    $stmt = database()->prepare(
        "SELECT id_turno, id_repartidor, estado, fecha_inicio, fecha_fin, fecha_pausa, tiempo_pausado_seg
         FROM turnos
         WHERE id_repartidor = :rep AND estado IN ('activo', 'pausado')
         ORDER BY fecha_inicio DESC LIMIT 1"
    );
    $stmt->execute(['rep' => $repartidorId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getTurnoActual(int $userId): array
{
    $rep = repartidorDelUsuario($userId);
    return [
        'turno' => turnoAbierto((int) $rep['id_repartidor']),
        'disponible' => (bool) $rep['disponible'],
    ];
}

function startTurno(int $userId): array
{
    $pdo = database();
    $pdo->beginTransaction();
    try {
        $rep = repartidorDelUsuario($userId);
        $repartidorId = (int) $rep['id_repartidor'];

        if (turnoAbierto($repartidorId)) {
            $pdo->rollBack();
            sendJson(['message' => 'Ya tenés un turno abierto. Reanudalo o finalizalo.'], 422);
        }

        $pdo->prepare("INSERT INTO turnos (id_repartidor, estado, fecha_inicio) VALUES (:rep, 'activo', NOW())")
            ->execute(['rep' => $repartidorId]);
        $idTurno = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE repartidores SET disponible = 1 WHERE id_repartidor = :rep')
            ->execute(['rep' => $repartidorId]);

        $pdo->commit();
        return [
            'message' => 'Turno iniciado. Ya estás disponible para recibir pedidos.',
            'turno' => ['id_turno' => $idTurno, 'estado' => TURNO_ACTIVO, 'fecha_inicio' => date('Y-m-d H:i:s')],
            'disponible' => true,
        ];
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function pauseTurno(int $userId): array
{
    $pdo = database();
    $pdo->beginTransaction();
    try {
        $rep = repartidorDelUsuario($userId);
        $repartidorId = (int) $rep['id_repartidor'];
        $abierto = turnoAbierto($repartidorId);

        if (!$abierto) {
            $pdo->rollBack();
            sendJson(['message' => 'No hay un turno activo para pausar'], 422);
        }
        if ($abierto['estado'] === TURNO_PAUSADO) {
            $pdo->rollBack();
            sendJson(['message' => 'El turno ya está en pausa'], 422);
        }

        $pdo->prepare("UPDATE turnos SET estado = 'pausado', fecha_pausa = NOW() WHERE id_turno = :id")
            ->execute(['id' => $abierto['id_turno']]);
        $pdo->prepare('UPDATE repartidores SET disponible = 0 WHERE id_repartidor = :rep')
            ->execute(['rep' => $repartidorId]);

        $pdo->commit();
        return [
            'message' => 'Turno en pausa. No recibirás nuevos pedidos.',
            'turno' => turnoAbierto($repartidorId),
            'disponible' => false,
        ];
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function resumeTurno(int $userId): array
{
    $pdo = database();
    $pdo->beginTransaction();
    try {
        $rep = repartidorDelUsuario($userId);
        $repartidorId = (int) $rep['id_repartidor'];
        $abierto = turnoAbierto($repartidorId);

        if (!$abierto || $abierto['estado'] !== TURNO_PAUSADO) {
            $pdo->rollBack();
            sendJson(['message' => 'No hay un turno en pausa para reanudar'], 422);
        }

        $segPausados = $abierto['fecha_pausa'] ? max(0, time() - strtotime($abierto['fecha_pausa'])) : 0;
        $pdo->prepare("UPDATE turnos SET estado = 'activo', fecha_pausa = NULL, tiempo_pausado_seg = tiempo_pausado_seg + :seg WHERE id_turno = :id")
            ->execute(['seg' => $segPausados, 'id' => $abierto['id_turno']]);
        $pdo->prepare('UPDATE repartidores SET disponible = 1 WHERE id_repartidor = :rep')
            ->execute(['rep' => $repartidorId]);

        $pdo->commit();
        return [
            'message' => 'Turno reanudado. Estás disponible nuevamente.',
            'turno' => turnoAbierto($repartidorId),
            'disponible' => true,
        ];
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function finishTurno(int $userId): array
{
    $pdo = database();
    $pdo->beginTransaction();
    try {
        $rep = repartidorDelUsuario($userId);
        $repartidorId = (int) $rep['id_repartidor'];
        $abierto = turnoAbierto($repartidorId);

        if (!$abierto) {
            $pdo->rollBack();
            sendJson(['message' => 'No hay un turno abierto para finalizar'], 422);
        }

        $segPausados = $abierto['estado'] === TURNO_PAUSADO && $abierto['fecha_pausa']
            ? max(0, time() - strtotime($abierto['fecha_pausa']))
            : 0;
        $pdo->prepare("UPDATE turnos SET estado = 'finalizado', fecha_fin = NOW(), fecha_pausa = NULL, tiempo_pausado_seg = tiempo_pausado_seg + :seg WHERE id_turno = :id")
            ->execute(['seg' => $segPausados, 'id' => $abierto['id_turno']]);
        $pdo->prepare('UPDATE repartidores SET disponible = 0 WHERE id_repartidor = :rep')
            ->execute(['rep' => $repartidorId]);

        $pdo->commit();
        return ['message' => 'Turno finalizado. Quedaste fuera de línea.', 'disponible' => false];
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}