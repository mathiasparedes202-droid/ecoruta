<?php

namespace App\Services;

use App\Repositories\ClienteRepository;
use App\Models\Cliente;
use Config\Database;

class ClienteService
{
    private ClienteRepository $clienteRepo;

    public function __construct()
    {
        $this->clienteRepo = new ClienteRepository();
    }

    public function listarClientes(): array
    {
        return $this->clienteRepo->all();
    }

    public function obtenerCliente(int $id): ?Cliente
    {
        return $this->clienteRepo->findById($id);
    }

    public function crearCliente(array $data): int
    {
        // Verificar que no exista otro cliente con el mismo CI/RUC
        $existente = $this->clienteRepo->findByCiRuc($data['ci_ruc']);
        if ($existente) {
            throw new \Exception('Ya existe un cliente con ese CI/RUC');
        }

        $cliente = new Cliente($data);
        return $this->clienteRepo->create($cliente);
    }

    public function actualizarCliente(int $id, array $data): bool
    {
        $cliente = $this->clienteRepo->findById($id);
        if (!$cliente) {
            throw new \Exception('Cliente no encontrado');
        }

        // Si se está cambiando el CI/RUC, verificar que no exista otro con ese valor
        if (isset($data['ci_ruc']) && $data['ci_ruc'] !== $cliente->ci_ruc) {
            $existente = $this->clienteRepo->findByCiRuc($data['ci_ruc']);
            if ($existente) {
                throw new \Exception('Ya existe otro cliente con ese CI/RUC');
            }
        }

        // Actualizar propiedades
        foreach ($data as $key => $value) {
            if (property_exists($cliente, $key)) {
                $cliente->$key = $value;
            }
        }

        return $this->clienteRepo->update($cliente);
    }

    public function eliminarCliente(int $id, bool $hardDelete = false): bool
    {
        return $this->clienteRepo->delete($id, $hardDelete);
    }

    public function obtenerDependencias(int $id): array
    {
        $db = (new Database())->connect();

        $dependencies = [];

        // Contar ventas del cliente: usamos la columna `estado_factura` presente en la tabla `venta`.
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM venta WHERE id_cliente = ? AND (estado_factura IS NULL OR estado_factura != ?)');
        $anulada = 'Anulada';
        $stmt->execute([$id, $anulada]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result && isset($result['count']) && (int)$result['count'] > 0) {
            $dependencies[] = [
                'type' => 'venta',
                'count' => (int)$result['count'],
                'message' => 'Ventas asociadas'
            ];
        }

        return $dependencies;
    }
}
