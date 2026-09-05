<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\Cliente;

class ClienteRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function all(): array
    {
        $query = "SELECT * FROM cliente WHERE estado = 1 ORDER BY razon_social ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Cliente($data), $rows);
    }

    public function findById(int $id): ?Cliente
    {
        $query = "SELECT * FROM cliente WHERE id_cliente = :id AND estado = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new Cliente($data) : null;
    }

    public function findByCiRuc(string $ciRuc): ?Cliente
    {
        $query = "SELECT * FROM cliente WHERE ruc_ci = :ruc_ci AND estado = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ruc_ci', $ciRuc, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new Cliente($data) : null;
    }

    public function create(Cliente $cliente): int
    {
        $rucCi = $cliente->ruc_ci ?? $cliente->ci_ruc ?? null;
        $razonSocial = $cliente->razon_social ?? $cliente->nombre ?? null;

        if (!$rucCi || !$razonSocial) {
            throw new \Exception('Faltan datos obligatorios del cliente: RUC/CI y razón social.');
        }

        $query = "INSERT INTO cliente (ruc_ci, razon_social, celular, estado, creado_por)
                  VALUES (:ruc_ci, :razon_social, :celular, :estado, :creado_por)";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ruc_ci', $rucCi, PDO::PARAM_STR);
        $stmt->bindParam(':razon_social', $razonSocial, PDO::PARAM_STR);
        $stmt->bindParam(':celular', $cliente->celular, PDO::PARAM_STR);
        $stmt->bindParam(':estado', $cliente->estado, PDO::PARAM_INT);
        $stmt->bindParam(':creado_por', $cliente->creado_por, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return (int) $this->db->lastInsertId();
        }

        throw new \Exception('No se pudo crear el cliente');
    }

    public function update(Cliente $cliente): bool
    {
        $rucCi = $cliente->ruc_ci ?? $cliente->ci_ruc ?? null;
        $razonSocial = $cliente->razon_social ?? $cliente->nombre ?? null;

        if (!$rucCi || !$razonSocial || !$cliente->id_cliente) {
            return false;
        }

        $query = "UPDATE cliente SET ruc_ci = :ruc_ci, razon_social = :razon_social, celular = :celular, estado = :estado WHERE id_cliente = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $cliente->id_cliente, PDO::PARAM_INT);
        $stmt->bindParam(':ruc_ci', $rucCi, PDO::PARAM_STR);
        $stmt->bindParam(':razon_social', $razonSocial, PDO::PARAM_STR);
        $stmt->bindParam(':celular', $cliente->celular, PDO::PARAM_STR);
        $stmt->bindParam(':estado', $cliente->estado, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete(int $id, bool $hardDelete = false): bool
    {
        if ($hardDelete) {
            $query = "DELETE FROM cliente WHERE id_cliente = :id";
        } else {
            $query = "UPDATE cliente SET estado = 0 WHERE id_cliente = :id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
