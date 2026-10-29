<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/request.php';
require_once __DIR__ . '/geografia.php';

/**
 * Directorio de clientes de cada comercio: cada local ve y gestiona solo los
 * suyos. Las direcciones se guardan con coordenadas para que el repartidor
 * encuentre el destino sin depender de la calle exacta escrita.
 */
function commerceIdOfUser(int $userId): ?int
{
    $stmt = database()->prepare('SELECT id_comercio FROM comercios WHERE id_usuario = :user LIMIT 1');
    $stmt->execute(['user' => $userId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id_comercio'] : null;
}

function listClientes(int $commerceId): array
{
    $statement = database()->prepare(
        'SELECT id_cliente, id_comercio, nombre, ruc_ci, telefono, direccion, lat, lng, referencia, fecha_creacion,
                (SELECT COUNT(*) FROM pedidos p WHERE p.id_cliente = c.id_cliente AND p.id_estado = 4) AS entregas,
                (SELECT COUNT(*) FROM pedidos p WHERE p.id_cliente = c.id_cliente AND p.id_estado IN (1, 2, 3)) AS en_curso
         FROM clientes c
         WHERE c.id_comercio = :commerce
         ORDER BY c.nombre'
    );
    $statement->execute(['commerce' => $commerceId]);
    return array_map(
        static fn (array $row): array => [
            'id_cliente' => (int) $row['id_cliente'],
            'id_comercio' => (int) $row['id_comercio'],
            'nombre' => $row['nombre'],
            'ruc_ci' => $row['ruc_ci'],
            'telefono' => $row['telefono'],
            'direccion' => $row['direccion'],
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'referencia' => $row['referencia'],
            'fecha_creacion' => $row['fecha_creacion'],
            'entregas' => (int) $row['entregas'],
            'en_curso' => (int) $row['en_curso'],
        ],
        $statement->fetchAll()
    );
}

function listAllClientes(): array
{
    $statement = database()->query(
        'SELECT c.*, COALESCE(com.razon_social, "Comercio") AS razon_social
         FROM clientes c
         LEFT JOIN comercios com ON com.id_comercio = c.id_comercio
         ORDER BY c.fecha_creacion DESC'
    );
    return $statement->fetchAll();
}

function clienteRucCiValido(mixed $value): bool
{
    if ($value === null || $value === '') {
        return true;
    }
    if (!is_string($value) && !is_int($value)) {
        return false;
    }

    $rucCi = trim((string) $value);
    return $rucCi === '' || preg_match('/^(?:\d{5,8}|\d{8}-\d)$/', $rucCi) === 1;
}

function validateClienteBody(array $body): void
{
    requireFields($body, ['nombre', 'direccion', 'lat', 'lng']);
    if (strlen(trim((string) $body['nombre'])) < 2) {
        sendJson(['message' => 'El nombre del cliente debe tener al menos 2 caracteres'], 422);
    }

    $lat = (float) $body['lat'];
    $lng = (float) $body['lng'];
    if (!coordsInParaguay($lat, $lng)) {
        sendJson(['message' => 'La ubicación del cliente debe estar dentro de Paraguay'], 422);
    }
    if (isset($body['telefono']) && $body['telefono'] !== '' && !preg_match('/^[+]?[0-9\s-]{7,20}$/', (string) $body['telefono'])) {
        sendJson(['message' => 'El teléfono del cliente no es válido'], 422);
    }
    if (!clienteRucCiValido($body['ruc_ci'] ?? null)) {
        sendJson(['message' => 'El RUC / C.I. debe tener entre 5 y 8 dígitos o formato RUC 12345678-9'], 422);
    }
}

function createCliente(int $commerceId, array $body): array
{
    validateClienteBody($body);

    $statement = database()->prepare(
        'INSERT INTO clientes (id_comercio, nombre, ruc_ci, telefono, direccion, lat, lng, referencia)
         VALUES (:commerce, :name, :ruc_ci, :phone, :address, :lat, :lng, :reference)'
    );
    $statement->execute([
        'commerce' => $commerceId,
        'name' => trim((string) $body['nombre']),
        'ruc_ci' => trim((string) ($body['ruc_ci'] ?? '')) ?: null,
        'phone' => trim((string) ($body['telefono'] ?? '')) ?: null,
        'address' => trim((string) $body['direccion']),
        'lat' => (float) $body['lat'],
        'lng' => (float) $body['lng'],
        'reference' => trim((string) ($body['referencia'] ?? '')) ?: null,
    ]);

    return findCliente((int) database()->lastInsertId());
}

function importClientes(int $commerceId, array $clientes): int
{
    if (count($clientes) > 500) {
        sendJson(['message' => 'Podés importar hasta 500 clientes por archivo'], 422);
    }

    $errors = [];
    foreach ($clientes as $index => $cliente) {
        $line = is_array($cliente) && isset($cliente['line']) && is_numeric($cliente['line'])
            ? (int) $cliente['line']
            : $index + 2;
        if (!is_array($cliente)) {
            $errors[] = ['line' => $line, 'errors' => ['fila inválida']];
            continue;
        }

        $rowErrors = [];
        $nombre = trim((string) ($cliente['nombre'] ?? ''));
        $direccion = trim((string) ($cliente['direccion'] ?? ''));
        $rucCi = $cliente['ruc_ci'] ?? null;
        $telefono = trim((string) ($cliente['telefono'] ?? ''));
        $lat = $cliente['lat'] ?? null;
        $lng = $cliente['lng'] ?? null;

        if (strlen($nombre) < 2) {
            $rowErrors[] = 'El nombre debe tener al menos 2 caracteres';
        }
        if ($direccion === '') {
            $rowErrors[] = 'La dirección es obligatoria';
        }
        if (!clienteRucCiValido($rucCi)) {
            $rowErrors[] = 'El RUC / C.I. debe tener entre 5 y 8 dígitos o formato RUC 12345678-9';
        }
        if ($telefono !== '' && !preg_match('/^[+]?[0-9\s-]{7,20}$/', $telefono)) {
            $rowErrors[] = 'El teléfono no es válido';
        }
        if (!is_numeric($lat) || !is_numeric($lng)) {
            $rowErrors[] = 'La latitud y longitud deben ser números';
        } elseif (!coordsInParaguay((float) $lat, (float) $lng)) {
            $rowErrors[] = 'La ubicación debe estar dentro de Paraguay';
        }
        if ($rowErrors !== []) {
            $errors[] = ['line' => $line, 'errors' => $rowErrors];
        }
    }

    if ($errors !== []) {
        sendJson([
            'message' => 'Hay filas con datos inválidos. No se importó ningún cliente.',
            'errors' => $errors,
        ], 422);
    }

    $pdo = database();
    $statement = $pdo->prepare(
        'INSERT INTO clientes (id_comercio, nombre, ruc_ci, telefono, direccion, lat, lng, referencia)
         VALUES (:commerce, :name, :ruc_ci, :phone, :address, :lat, :lng, :reference)'
    );

    $pdo->beginTransaction();
    try {
        foreach ($clientes as $cliente) {
            $statement->execute([
                'commerce' => $commerceId,
                'name' => trim((string) $cliente['nombre']),
                'ruc_ci' => trim((string) ($cliente['ruc_ci'] ?? '')) ?: null,
                'phone' => trim((string) ($cliente['telefono'] ?? '')) ?: null,
                'address' => trim((string) $cliente['direccion']),
                'lat' => (float) $cliente['lat'],
                'lng' => (float) $cliente['lng'],
                'reference' => trim((string) ($cliente['referencia'] ?? '')) ?: null,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return count($clientes);
}

function findCliente(int $clienteId): array
{
    $statement = database()->prepare(
        'SELECT id_cliente, id_comercio, nombre, ruc_ci, telefono, direccion, lat, lng, referencia, fecha_creacion
         FROM clientes WHERE id_cliente = :id LIMIT 1'
    );
    $statement->execute(['id' => $clienteId]);
    $cliente = $statement->fetch();
    if (!$cliente) {
        sendJson(['message' => 'Cliente no encontrado'], 404);
    }
    return $cliente;
}

function assertClienteBelongsTo(int $clienteId, int $commerceId): void
{
    $cliente = findCliente($clienteId);
    if ((int) $cliente['id_comercio'] !== $commerceId) {
        sendJson(['message' => 'El cliente no pertenece a tu comercio'], 403);
    }
}

function updateCliente(int $clienteId, int $commerceId, array $body): array
{
    assertClienteBelongsTo($clienteId, $commerceId);
    if (isset($body['lat'], $body['lng'])) {
        if (!coordsInParaguay((float) $body['lat'], (float) $body['lng'])) {
            sendJson(['message' => 'La ubicación del cliente debe estar dentro de Paraguay'], 422);
        }
    }

    $sets = [];
    $params = ['id' => $clienteId];
    $textFields = ['nombre', 'telefono', 'direccion', 'referencia'];
    foreach ($textFields as $field) {
        if (isset($body[$field]) && (string) $body[$field] !== '') {
            $sets[] = "$field = :$field";
            $params[$field] = trim((string) $body[$field]);
        }
    }
    if (array_key_exists('ruc_ci', $body)) {
        $rucCi = trim((string) $body['ruc_ci']);
        if (!clienteRucCiValido($rucCi)) {
            sendJson(['message' => 'El RUC / C.I. debe tener entre 5 y 8 dígitos o formato RUC 12345678-9'], 422);
        }
        $sets[] = 'ruc_ci = :ruc_ci';
        $params['ruc_ci'] = $rucCi !== '' ? $rucCi : null;
    }
    if (isset($body['lat']) && $body['lat'] !== '' && is_numeric($body['lat'])) {
        $sets[] = 'lat = :lat';
        $params['lat'] = (float) $body['lat'];
    }
    if (isset($body['lng']) && $body['lng'] !== '' && is_numeric($body['lng'])) {
        $sets[] = 'lng = :lng';
        $params['lng'] = (float) $body['lng'];
    }

    if (empty($sets)) {
        sendJson(['message' => 'No hay datos del cliente para actualizar'], 422);
    }

    database()->prepare('UPDATE clientes SET ' . implode(', ', $sets) . ' WHERE id_cliente = :id')->execute($params);
    return findCliente($clienteId);
}

function deleteCliente(int $clienteId, int $commerceId): array
{
    assertClienteBelongsTo($clienteId, $commerceId);
    database()->prepare('DELETE FROM clientes WHERE id_cliente = :id')->execute(['id' => $clienteId]);
    return ['message' => '¡Cliente eliminado!'];
}