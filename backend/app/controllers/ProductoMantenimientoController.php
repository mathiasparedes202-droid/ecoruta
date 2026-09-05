<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use App\Services\ProductoService;
use App\Repositories\ProductoRepository;

class ProductoMantenimientoController extends Controller
{
    private ProductoRepository $repo;
    private ProductoService $productoService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->repo = new ProductoRepository();
        $this->productoService = new ProductoService();
    }

    public function index(): void
    {
        $productos = $this->repo->all();
        $productosConReceta = array_map(function ($producto) {
            $data = $producto->toArray();
            $recipePayload = $this->repo->getRecipePayload($producto->id_producto);
            $data['receta'] = $recipePayload;
            $data['receta_detalles'] = $recipePayload['receta_detalles'] ?? [];
            return $data;
        }, $productos);

        $this->response->success('Lista de productos', $productosConReceta);
    }

    public function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $producto = $this->repo->findById($id);
        if (!$producto) {
            $this->response->error('Producto no encontrado', 404);
            return;
        }

        $data = $producto->toArray();
        $recipePayload = $this->repo->getRecipePayload($id);
        $data['receta'] = $recipePayload;
        $data['receta_detalles'] = $recipePayload['receta_detalles'] ?? [];

        $this->response->success('Producto encontrado', $data);
    }

    public function store(): void
    {
        $body = $this->request->getBody();
        $usuario = $this->request->getUser();
        $usuarioId = $this->resolveUsuarioId($usuario);

        try {
            // Validación de datos requeridos
            if (empty($body['nombre_producto'])) {
                $this->response->error('El nombre del producto es requerido', 422);
                return;
            }

            error_log("ProductoMantenimientoController::store - Iniciando creación de producto: " . json_encode($body));

            $id = $this->productoService->crearProductoConReceta($body, $usuarioId);

            error_log("ProductoMantenimientoController::store - Producto creado exitosamente con ID: $id");
            $this->response->success('Producto creado correctamente', ['id' => $id, 'id_producto' => $id]);
        } catch (\InvalidArgumentException $e) {
            error_log("ProductoMantenimientoController::store - Validation Error: " . $e->getMessage());
            $this->response->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            error_log("ProductoMantenimientoController::store - Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->response->error('Error al crear producto: ' . $e->getMessage(), 500);
        } catch (\Throwable $e) {
            error_log("ProductoMantenimientoController::store - Throwable Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->response->error('Error interno del servidor al crear producto. Consulta los logs para más información.', 500);
        }
    }

    public function update(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $body = $this->request->getBody();
        $usuario = $this->request->getUser();
        $usuarioId = $this->resolveUsuarioId($usuario);

        try {
            if ($id <= 0) {
                $this->response->error('ID de producto inválido', 422);
                return;
            }

            if (empty($body['nombre_producto'])) {
                $this->response->error('El nombre del producto es requerido', 422);
                return;
            }

            error_log("ProductoMantenimientoController::update - Actualizando producto ID: $id con datos: " . json_encode($body));

            $ok = $this->productoService->actualizarProductoConReceta($id, $body, $usuarioId);

            error_log("ProductoMantenimientoController::update - Producto actualizado exitosamente ID: $id");
            $this->response->success('Producto actualizado correctamente', ['ok' => $ok]);
        } catch (\InvalidArgumentException $e) {
            error_log("ProductoMantenimientoController::update - Validation Error: " . $e->getMessage());
            $this->response->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            error_log("ProductoMantenimientoController::update - Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->response->error('Error al actualizar producto: ' . $e->getMessage(), 500);
        } catch (\Throwable $e) {
            error_log("ProductoMantenimientoController::update - Throwable Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->response->error('Error interno del servidor al actualizar producto. Consulta los logs para más información.', 500);
        }
    }

    public function destroy(array $params): void
    {
        $id = (int)($params['id'] ?? 0);

        try {
            $query = "UPDATE producto SET estado = 0 WHERE id_producto = :id_producto";
            $stmt = (new \Config\Database())->connect()->prepare($query);
            $stmt->execute(['id_producto' => $id]);
            $this->response->success('Producto eliminado (soft-delete)');
        } catch (\Throwable $e) {
            $this->response->error('Error al eliminar producto: ' . $e->getMessage(), 500);
        }
    }

    private function resolveUsuarioId($usuario): ?int
    {
        if (!$usuario) {
            return null;
        }

        if (is_array($usuario)) {
            return (int)($usuario['id_usuario'] ?? $usuario['id'] ?? 0);
        }

        return (int)($usuario->id_usuario ?? $usuario->id ?? 0);
    }
}
