<?php

namespace App\Services;

use App\Repositories\InsumoRepository;

class StockService
{
    private InsumoRepository $insumoRepo;

    public function __construct()
    {
        $this->insumoRepo = new InsumoRepository();
    }

    public function ajustarStock(array $data, int $usuarioId): bool
    {
        $idInsumo = (int)($data['id_insumo'] ?? 0);
        $cantidad = isset($data['cantidad']) ? (float)$data['cantidad'] : 0;
        $tipo = strtolower(trim($data['tipo'] ?? 'entrada'));

        if (!$idInsumo) {
            throw new \InvalidArgumentException('Seleccione un insumo válido');
        }

        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a cero');
        }

        $insumo = $this->insumoRepo->findById($idInsumo);
        if (!$insumo) {
            throw new \Exception('El insumo seleccionado no existe');
        }

        if ($tipo === 'salida') {
            if ($insumo->stock < $cantidad) {
                throw new \Exception('No hay suficiente stock para realizar la salida');
            }
            return $this->insumoRepo->decrementarStock($idInsumo, $cantidad);
        }

        return $this->insumoRepo->incrementarStock($idInsumo, $cantidad);
    }
}
