<?php

namespace App\Services;

use App\Repositories\ProductoRepository;

class ProductoService
{
    private ProductoRepository $productoRepo;

    public function __construct()
    {
        $this->productoRepo = new ProductoRepository();
    }

    public function listarProductos(): array
    {
        $productos = $this->productoRepo->all();
        return array_map(fn($producto) => $producto->toArray(), $productos);
    }

    public function obtenerProducto(int $id): ?array
    {
        $producto = $this->productoRepo->findById($id);
        if (!$producto) {
            return null;
        }

        $receta = $this->productoRepo->findActiveRecipeByProducto($id);
        $detalles = [];

        if ($receta) {
            $detalles = $this->productoRepo->findRecipeDetails((int)$receta['id_receta']);
        }

        return [
            'producto' => $producto->toArray(),
            'receta' => $receta,
            'receta_detalles' => $detalles,
        ];
    }

    public function crearProductoConReceta(array $data, int $usuarioId): int
    {
        if (empty($data['nombre_producto'])) {
            throw new \InvalidArgumentException('El nombre del producto es requerido');
        }

        if (trim($data['nombre_producto']) === '') {
            throw new \InvalidArgumentException('El nombre del producto no puede estar vacío');
        }

        $detalles = [];

        if (!empty($data['receta_detalles']) && is_array($data['receta_detalles'])) {
            $detalles = $data['receta_detalles'];
        } elseif (!empty($data['receta']['detalles']) && is_array($data['receta']['detalles'])) {
            $detalles = $data['receta']['detalles'];
        } elseif (!empty($data['receta']) && is_array($data['receta'])) {
            $detalles = $data['receta'];
        }

        error_log("ProductoService::crearProductoConReceta - Nombre: {$data['nombre_producto']}, Detalles receta: " . count($detalles));

        try {
            return $this->productoRepo->createWithReceta($data, ['receta' => ['detalles' => $detalles], 'detalles' => $detalles], $usuarioId);
        } catch (\Throwable $e) {
            error_log("ProductoService::crearProductoConReceta - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    public function actualizarProductoConReceta(int $id, array $data, int $usuarioId): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID del producto inválido');
        }

        if (empty($data['nombre_producto'])) {
            throw new \InvalidArgumentException('El nombre del producto es requerido');
        }

        if (trim($data['nombre_producto']) === '') {
            throw new \InvalidArgumentException('El nombre del producto no puede estar vacío');
        }

        $detalles = [];

        if (!empty($data['receta_detalles']) && is_array($data['receta_detalles'])) {
            $detalles = $data['receta_detalles'];
        } elseif (!empty($data['receta']['detalles']) && is_array($data['receta']['detalles'])) {
            $detalles = $data['receta']['detalles'];
        } elseif (!empty($data['receta']) && is_array($data['receta'])) {
            $detalles = $data['receta'];
        }

        error_log("ProductoService::actualizarProductoConReceta - ID: $id, Nombre: {$data['nombre_producto']}, Detalles receta: " . count($detalles));

        try {
            return $this->productoRepo->updateWithReceta($id, $data, $detalles, $usuarioId);
        } catch (\Throwable $e) {
            error_log("ProductoService::actualizarProductoConReceta - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }
}
