<?php

namespace App\Services;

use App\Repositories\InsumoRepository;
use App\Repositories\CompraRepository;
use App\Models\Insumo;

class InsumoService
{
    private InsumoRepository $insumoRepo;
    private CompraRepository $compraRepo;

    public function __construct()
    {
        $this->insumoRepo = new InsumoRepository();
        $this->compraRepo = new CompraRepository();
    }

    public function listarInsumos(): array
    {
        $insumos = $this->insumoRepo->all();
        return array_map(fn($insumo) => $this->toArray($insumo), $insumos);
    }

    public function obtenerInsumo(int $id): ?array
    {
        $insumo = $this->insumoRepo->findById($id);
        return $insumo ? $this->toArray($insumo) : null;
    }

    private function toArray(Insumo $insumo): array
    {
        $nombreUnidad = $this->insumoRepo->obtenerNombreUnidad((int)$insumo->id_unidad);

        return [
            'id_insumo' => (int)$insumo->id_insumo,
            'nombre_insumo' => (string)$insumo->nombre_insumo,
            'descripcion' => $insumo->descripcion ? (string)$insumo->descripcion : null,
            'id_tipo_insumo' => (int)$insumo->id_tipo_insumo,
            'id_variedad' => $insumo->id_variedad ? (int)$insumo->id_variedad : null,
            'id_color' => $insumo->id_color ? (int)$insumo->id_color : null,
            'id_categoria' => (int)$insumo->id_categoria,
            'id_unidad' => (int)$insumo->id_unidad,
            'nombre_unidad' => $nombreUnidad ? (string)$nombreUnidad : null,
            'precio_compra' => $insumo->precio_compra !== null ? round((float)$insumo->precio_compra, 2) : null,
            'stock' => (float)$insumo->stock,
            'estado' => (int)$insumo->estado,
            'fecha_creacion' => $insumo->fecha_creacion,
            'creado_por' => $insumo->creado_por ? (int)$insumo->creado_por : null,
        ];
    }

    public function crearInsumo(array $data): int
    {
        $insumo = new Insumo($data);
        return $this->insumoRepo->create($insumo);
    }

    public function actualizarInsumo(int $id, array $data): bool
    {
        $insumo = $this->insumoRepo->findById($id);
        if (!$insumo) {
            throw new \Exception('Insumo no encontrado');
        }

        // Actualizar propiedades
        foreach ($data as $key => $value) {
            if (property_exists($insumo, $key)) {
                $insumo->$key = $value;
            }
        }

        return $this->insumoRepo->update($insumo);
    }

    public function eliminarInsumo(int $id, bool $hardDelete = false): bool
    {
        $compras = $this->compraRepo->findByInsumo($id);
        if (count($compras) > 0) {
            throw new \Exception('No se puede eliminar el insumo porque tiene compras asociadas');
        }

        return $this->insumoRepo->delete($id, $hardDelete);
    }
}
