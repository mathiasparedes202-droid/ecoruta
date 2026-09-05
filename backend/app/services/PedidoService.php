<?php

namespace App\Services;

use App\Repositories\PedidoRepository;

class PedidoService
{
    private PedidoRepository $pedidoRepo;

    public function __construct()
    {
        $this->pedidoRepo = new PedidoRepository();
    }

    public function listarOrdenesProduccion(): array
    {
        return $this->pedidoRepo->listProductionOrders();
    }

    public function obtenerOrdenProduccion(int $id): ?array
    {
        return $this->pedidoRepo->findProductionOrderById($id);
    }

    public function crearOrdenProduccion(array $data, int $usuarioId): int
    {
        return $this->pedidoRepo->createProductionOrder($data, $usuarioId);
    }

    public function obtenerOrdenProduccionIdPorPedido(int $pedidoId): ?int
    {
        return $this->pedidoRepo->findOrdenProduccionIdByPedidoId($pedidoId);
    }

    public function actualizarOrdenProduccion(int $id, array $data): bool
    {
        return $this->pedidoRepo->updateProductionOrder($id, $data);
    }

    /** Cierra y archiva la orden cuando su pedido ya fue facturado. */
    public function cerrarOrdenPorVenta(int $pedidoId): bool
    {
        return $this->pedidoRepo->closeProductionOrderForSale($pedidoId);
    }

    public function eliminarOrdenProduccion(int $id): bool
    {
        return $this->pedidoRepo->deleteProductionOrder($id);
    }
}
