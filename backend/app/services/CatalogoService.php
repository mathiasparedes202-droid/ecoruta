<?php

namespace App\Services;

use App\Repositories\CatalogoRepository;
use InvalidArgumentException;

class CatalogoService
{
    private CatalogoRepository $catalogoRepo;

    public function __construct()
    {
        $this->catalogoRepo = new CatalogoRepository();
    }

    public function listarCatalogos(string $section): array
    {
        return array_map(fn($item) => $this->normalize($section, $item), $this->catalogoRepo->all($section));
    }

    public function obtenerCatalogo(string $section, int $id): ?array
    {
        $item = $this->catalogoRepo->findById($section, $id);
        return $item ? $this->normalize($section, $item) : null;
    }

    public function crearCatalogo(string $section, array $data): array
    {
        $this->validateInput($section, $data);

        $payload = $this->buildPayload($section, $data);
        $id = $this->catalogoRepo->create($section, $payload);

        return $this->obtenerCatalogo($section, $id);
    }

    public function actualizarCatalogo(string $section, int $id, array $data): array
    {
        $item = $this->catalogoRepo->findById($section, $id);
        if (!$item) {
            throw new InvalidArgumentException('Elemento no encontrado');
        }

        $payload = $this->buildPayload($section, $data, $item);
        $this->catalogoRepo->update($section, $id, $payload);

        return $this->obtenerCatalogo($section, $id);
    }

    public function eliminarCatalogo(string $section, int $id): bool
    {
        return $this->catalogoRepo->delete($section, $id);
    }

    private function validateInput(string $section, array $data): void
    {
        if (empty(trim($data['nombre'] ?? ''))) {
            throw new InvalidArgumentException('El nombre es obligatorio');
        }

        if ($section === 'colores' && !empty($data['codigo_hex']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['codigo_hex'])) {
            throw new InvalidArgumentException('El color debe ser un código hexadecimal válido, por ejemplo #FF0000');
        }
    }

    private function buildPayload(string $section, array $data, array $existing = []): array
    {
        $payload = [];

        switch ($section) {
            case 'categorias':
                $payload['nombre_categoria'] = $data['nombre'] ?? $existing['nombre_categoria'] ?? '';
                $payload['descripcion'] = $data['descripcion'] ?? $existing['descripcion'] ?? null;
                $payload['estado'] = isset($data['estado']) ? (int)$data['estado'] : ($existing['estado'] ?? 1);
                break;
            case 'tipo_insumo':
                $payload['nombre_tipo'] = $data['nombre'] ?? $existing['nombre_tipo'] ?? '';
                $payload['descripcion'] = $data['descripcion'] ?? $existing['descripcion'] ?? null;
                $payload['estado'] = isset($data['estado']) ? (int)$data['estado'] : ($existing['estado'] ?? 1);
                break;
            case 'variedades':
                $payload['nombre_variedad'] = $data['nombre'] ?? $existing['nombre_variedad'] ?? '';
                $payload['descripcion'] = $data['descripcion'] ?? $existing['descripcion'] ?? null;
                $payload['estado'] = isset($data['estado']) ? (int)$data['estado'] : ($existing['estado'] ?? 1);
                break;
            case 'unidades':
                $payload['nombre_unidad'] = $data['nombre'] ?? $existing['nombre_unidad'] ?? '';
                $payload['simbolo'] = $data['simbolo'] ?? $existing['simbolo'] ?? null;
                $payload['estado'] = isset($data['estado']) ? (int)$data['estado'] : ($existing['estado'] ?? 1);
                break;
            case 'colores':
                $payload['nombre_color'] = $data['nombre'] ?? $existing['nombre_color'] ?? '';
                $payload['codigo_hex'] = $data['codigo_hex'] ?? $existing['codigo_hex'] ?? null;
                $payload['estado'] = isset($data['estado']) ? (int)$data['estado'] : ($existing['estado'] ?? 1);
                break;
            default:
                throw new InvalidArgumentException('Sección inválida para catálogo');
        }

        return $payload;
    }

    private function normalize(string $section, array $item): array
    {
        switch ($section) {
            case 'categorias':
                return [
                    'id' => (int)$item['id_categoria'],
                    'nombre' => $item['nombre_categoria'],
                    'descripcion' => $item['descripcion'] ?? null,
                    'estado' => (int)$item['estado'],
                ];
            case 'unidades':
                return [
                    'id' => (int)$item['id_unidad'],
                    'nombre' => $item['nombre_unidad'],
                    'simbolo' => $item['simbolo'] ?? null,
                    'estado' => (int)$item['estado'],
                ];
            case 'tipo_insumo':
                return [
                    'id' => (int)$item['id_tipo_insumo'],
                    'nombre' => $item['nombre_tipo'],
                    'descripcion' => $item['descripcion'] ?? null,
                    'estado' => (int)$item['estado'],
                ];
            case 'colores':
                return [
                    'id' => (int)$item['id_color'],
                    'nombre' => $item['nombre_color'],
                    'codigo_hex' => $item['codigo_hex'] ?? null,
                    'estado' => (int)$item['estado'],
                ];
            case 'variedades':
                return [
                    'id' => (int)$item['id_variedad'],
                    'nombre' => $item['nombre_variedad'],
                    'descripcion' => $item['descripcion'] ?? null,
                    'estado' => (int)$item['estado'],
                ];
            default:
                throw new InvalidArgumentException('Sección inválida para catálogo');
        }
    }
}
