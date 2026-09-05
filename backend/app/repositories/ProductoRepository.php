<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\Producto;

class ProductoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    private function hasPrecioCompraColumn(): bool
    {
        try {
            $query = "SHOW COLUMNS FROM insumo LIKE 'precio_compra'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // If the query fails, assume column doesn't exist
            return false;
        }
    }

    private function ensurePrecioCompraColumn(): void
    {
        if ($this->hasPrecioCompraColumn()) {
            return;
        }

        try {
            $this->db->exec("ALTER TABLE insumo ADD COLUMN precio_compra DECIMAL(12,2) NULL AFTER id_unidad");
        } catch (\Throwable $e) {
            // Column might already exist or other error - that's fine, it will still work
            error_log("Warning: Could not add precio_compra column to insumo table: " . $e->getMessage());
        }
    }

    public function all(): array
    {
        $query = "SELECT * FROM producto WHERE estado = 1 ORDER BY nombre_producto ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($data) => new Producto($data), $rows);
    }

    public function findById(int $id): ?Producto
    {
        $query = "SELECT * FROM producto WHERE id_producto = :id_producto LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_producto', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new Producto($data) : null;
    }

    public function getRecipePayload(int $idProducto): array
    {
        $receta = $this->findActiveRecipeByProducto($idProducto);
        if (!$receta) {
            return [
                'id_receta' => null,
                'id_producto' => $idProducto,
                'detalles' => [],
                'receta_detalles' => [],
            ];
        }

        $detalles = $this->findRecipeDetails((int)$receta['id_receta']);

        return [
            'id_receta' => (int)$receta['id_receta'],
            'id_producto' => $idProducto,
            'detalles' => $detalles,
            'receta_detalles' => $detalles,
        ];
    }

    public function findByName(string $nombreProducto): ?Producto
    {
        $query = "SELECT * FROM producto WHERE nombre_producto = :nombre_producto LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new Producto($data) : null;
    }

    public function create(array $data): int
    {
        try {
            $query = "INSERT INTO producto (nombre_producto, tipo_producto, descripcion, costo_produccion, precio_base, estado, es_personalizable, creado_por) VALUES (:nombre_producto, :tipo_producto, :descripcion, :costo_produccion, :precio_base, :estado, :es_personalizable, :creado_por)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':nombre_producto', $data['nombre_producto'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':tipo_producto', $data['tipo_producto'] ?? 'Ramo', PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', $data['descripcion'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':costo_produccion', isset($data['costo_produccion']) ? $data['costo_produccion'] : 0, PDO::PARAM_STR);
            $stmt->bindValue(':precio_base', isset($data['precio_base']) ? $data['precio_base'] : 0, PDO::PARAM_STR);
            $stmt->bindValue(':estado', $data['estado'] ?? 1, PDO::PARAM_INT);
            $stmt->bindValue(':es_personalizable', $data['es_personalizable'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':creado_por', $data['creado_por'] ?? null, PDO::PARAM_INT);

            $result = $stmt->execute();

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new \Exception('No se pudo crear el producto. SQL Error: ' . implode(', ', $errorInfo));
            }

            $id = (int)$this->db->lastInsertId();
            error_log("ProductoRepository::create - Producto insertado exitosamente con ID: $id");
            return $id;
        } catch (\Throwable $e) {
            error_log("ProductoRepository::create - Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function findActiveRecipeByProducto(int $idProducto): ?array
    {
        $query = "SELECT * FROM receta_produccion WHERE id_producto = :id_producto AND estado = 1 ORDER BY version DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public function findRecipeDetails(int $idReceta): array
    {
        $query = "SELECT rd.id_receta_detalle,
                         rd.id_receta,
                         rd.id_insumo,
                         rd.cantidad,
                         rd.creado_por,
                         i.nombre_insumo,
                         i.descripcion AS insumo_descripcion,
                         i.stock AS stock_actual
                  FROM receta_detalle rd
                  JOIN insumo i ON rd.id_insumo = i.id_insumo
                  WHERE rd.id_receta = :id_receta
                  ORDER BY rd.id_receta_detalle";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_receta', $idReceta, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateCostoProduccion(array $detalles): float
    {
        $this->ensurePrecioCompraColumn();
        $costoProduccion = 0.0;
        $precioQuery = $this->db->prepare("SELECT COALESCE(i.precio_compra, 0) AS precio FROM insumo i WHERE i.id_insumo = :id_insumo LIMIT 1");

        foreach ($detalles as $d) {
            $idInsumo = (int)($d['id_insumo'] ?? 0);
            $cantidad = isset($d['cantidad']) ? (float)$d['cantidad'] : 0;
            if ($idInsumo <= 0 || $cantidad <= 0) {
                continue;
            }

            $precioQuery->bindParam(':id_insumo', $idInsumo, PDO::PARAM_INT);
            $precioQuery->execute();
            $row = $precioQuery->fetch(PDO::FETCH_ASSOC);
            $precio = isset($row['precio']) ? (float)$row['precio'] : 0;
            $costoProduccion += $precio * $cantidad;
        }

        return $costoProduccion;
    }

    private function calculatePrecioVenta(float $costoProduccion): float
    {
        $costoProduccion = max(0.0, $costoProduccion);
        $manoObra = round($costoProduccion * 0.20, 2);
        $utilidades = round($costoProduccion * 0.10, 2);
        return round($costoProduccion + $manoObra + $utilidades, 2);
    }

    private function resolvePrecioBase(array $productoData, float $costoProduccion): float
    {
        $precioBase = $productoData['precio_base'] ?? null;
        if ($precioBase !== null && $precioBase !== '' && is_numeric($precioBase)) {
            return round((float)$precioBase, 2);
        }

        return $this->calculatePrecioVenta($costoProduccion);
    }

    public function getCostoYPrecioVenta(int $idProducto): array
    {
        $producto = $this->findById($idProducto);
        if (!$producto) {
            return ['costo_produccion' => 0.0, 'precio_venta' => 0.0];
        }

        $receta = $this->findActiveRecipeByProducto($idProducto);
        if ($receta) {
            $detalles = $this->findRecipeDetails((int)$receta['id_receta']);
            $costoProduccion = $this->calculateCostoProduccion($detalles);
            $precioVenta = $this->calculatePrecioVenta($costoProduccion);
        } else {
            $costoProduccion = (float)$producto->costo_produccion;
            $precioVenta = (float)$producto->precio_base;
            if ($precioVenta <= 0) {
                $precioVenta = $this->calculatePrecioVenta($costoProduccion);
            }
        }

        return [
            'costo_produccion' => round($costoProduccion, 2),
            'precio_venta' => round($precioVenta, 2),
        ];
    }

    public function getPrecioVenta(int $idProducto): float
    {
        $pricing = $this->getCostoYPrecioVenta($idProducto);
        return (float)$pricing['precio_venta'];
    }

    public function createWithReceta(array $productoData, array $recetaData, int $usuarioId): int
    {
        try {
            error_log("ProductoRepository::createWithReceta - Iniciando transacción para: {$productoData['nombre_producto']}");

            $this->db->beginTransaction();

            $detalles = $recetaData['detalles'] ?? [];
            error_log("ProductoRepository::createWithReceta - Calculando costo de producción con " . count($detalles) . " detalles");

            $costoProduccion = $this->calculateCostoProduccion($detalles);
            error_log("ProductoRepository::createWithReceta - Costo producción calculado: $costoProduccion");

            $precioBase = $this->resolvePrecioBase($productoData, $costoProduccion);
            error_log("ProductoRepository::createWithReceta - Precio base resuelto: $precioBase");

            // Crear producto
            $productoId = $this->create([
                'nombre_producto' => $productoData['nombre_producto'],
                'tipo_producto' => $productoData['tipo_producto'] ?? 'Ramo',
                'descripcion' => $productoData['descripcion'] ?? null,
                'costo_produccion' => $costoProduccion,
                'precio_base' => $precioBase,
                'estado' => $productoData['estado'] ?? 1,
                'es_personalizable' => $productoData['es_personalizable'] ?? 1,
                'creado_por' => $usuarioId,
            ]);

            error_log("ProductoRepository::createWithReceta - Producto creado con ID: $productoId");

            // Crear receta de producción
            $recetaQuery = "INSERT INTO receta_produccion (id_producto, fecha_creacion, estado, creado_por, version) VALUES (:id_producto, :fecha_creacion, 1, :creado_por, :version)";
            $stmtRec = $this->db->prepare($recetaQuery);
            $version = 1;
            $result = $stmtRec->execute([
                'id_producto' => $productoId,
                'fecha_creacion' => date('Y-m-d H:i:s'),
                'creado_por' => $usuarioId,
                'version' => $version,
            ]);

            if (!$result) {
                throw new \Exception('No se pudo crear la receta de producción: ' . implode(', ', $stmtRec->errorInfo()));
            }

            $idReceta = (int)$this->db->lastInsertId();
            error_log("ProductoRepository::createWithReceta - Receta creada con ID: $idReceta");

            $detailQuery = "INSERT INTO receta_detalle (id_receta, id_insumo, cantidad, creado_por) VALUES (:id_receta, :id_insumo, :cantidad, :creado_por)";
            $detailStmt = $this->db->prepare($detailQuery);

            foreach ($detalles as $d) {
                $idInsumo = (int)($d['id_insumo'] ?? 0);
                $cantidad = isset($d['cantidad']) ? (float)$d['cantidad'] : 0;
                if ($idInsumo <= 0 || $cantidad <= 0) {
                    error_log("ProductoRepository::createWithReceta - Saltando detalle: idInsumo=$idInsumo, cantidad=$cantidad");
                    continue;
                }

                $result = $detailStmt->execute([
                    'id_receta' => $idReceta,
                    'id_insumo' => $idInsumo,
                    'cantidad' => $cantidad,
                    'creado_por' => $usuarioId,
                ]);

                if (!$result) {
                    throw new \Exception('No se pudo crear detalles de receta: ' . implode(', ', $detailStmt->errorInfo()));
                }
            }

            error_log("ProductoRepository::createWithReceta - Todos los detalles insertados, commit de transacción");
            $this->db->commit();
            return $productoId;
        } catch (\Throwable $e) {
            error_log("ProductoRepository::createWithReceta - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            try {
                $this->db->rollBack();
            } catch (\Throwable $rollbackError) {
                error_log("ProductoRepository::createWithReceta - Error en rollback: " . $rollbackError->getMessage());
            }
            throw $e;
        }
    }

    public function recalcularCostoProduccionPorProducto(int $idProducto): bool
    {
        $pricing = $this->getCostoYPrecioVenta($idProducto);

        $query = "UPDATE producto SET costo_produccion = :costo_produccion, precio_base = :precio_base WHERE id_producto = :id_producto";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':costo_produccion', round((float)$pricing['costo_produccion'], 2), PDO::PARAM_STR);
        $stmt->bindValue(':precio_base', round((float)$pricing['precio_venta'], 2), PDO::PARAM_STR);
        $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function actualizarCostosProductosPorInsumo(int $idInsumo): void
    {
        $query = "SELECT DISTINCT p.id_producto
                  FROM producto p
                  JOIN receta_produccion rp ON rp.id_producto = p.id_producto AND rp.estado = 1
                  JOIN receta_detalle rd ON rd.id_receta = rp.id_receta AND rd.id_insumo = :id_insumo";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_insumo', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->recalcularCostoProduccionPorProducto((int)$row['id_producto']);
        }
    }

    public function updateWithReceta(int $idProducto, array $productoData, array $detalles, int $usuarioId): bool
    {
        try {
            error_log("ProductoRepository::updateWithReceta - Iniciando actualización del producto ID: $idProducto");

            $this->db->beginTransaction();

            $costoProduccion = $this->calculateCostoProduccion($detalles);
            error_log("ProductoRepository::updateWithReceta - Costo producción calculado: $costoProduccion");

            $precioBase = $this->resolvePrecioBase($productoData, $costoProduccion);
            error_log("ProductoRepository::updateWithReceta - Precio base resuelto: $precioBase");

            // Actualizar producto
            $updateQuery = "UPDATE producto SET nombre_producto = :nombre_producto, descripcion = :descripcion, tipo_producto = :tipo_producto, costo_produccion = :costo_produccion, precio_base = :precio_base, es_personalizable = :es_personalizable WHERE id_producto = :id_producto";
            $stmtUpdate = $this->db->prepare($updateQuery);
            $result = $stmtUpdate->execute([
                'nombre_producto' => $productoData['nombre_producto'] ?? '',
                'descripcion' => $productoData['descripcion'] ?? null,
                'tipo_producto' => $productoData['tipo_producto'] ?? 'Ramo',
                'costo_produccion' => $costoProduccion,
                'precio_base' => $precioBase,
                'es_personalizable' => $productoData['es_personalizable'] ?? 1,
                'id_producto' => $idProducto,
            ]);

            if (!$result) {
                throw new \Exception('No se pudo actualizar el producto: ' . implode(', ', $stmtUpdate->errorInfo()));
            }

            error_log("ProductoRepository::updateWithReceta - Producto actualizado");

            // Marcar recetas previas como inactivas
            $deactivate = $this->db->prepare("UPDATE receta_produccion SET estado = 0 WHERE id_producto = :id_producto");
            $result = $deactivate->execute(['id_producto' => $idProducto]);

            if (!$result) {
                throw new \Exception('No se pudo desactivar recetas previas: ' . implode(', ', $deactivate->errorInfo()));
            }

            error_log("ProductoRepository::updateWithReceta - Recetas previas desactivadas");

            // Insertar nueva receta
            $recetaQuery = "INSERT INTO receta_produccion (id_producto, fecha_creacion, estado, creado_por, version) VALUES (:id_producto, :fecha_creacion, 1, :creado_por, :version)";
            $stmtRec = $this->db->prepare($recetaQuery);

            // Calcular nueva versión
            $verStmt = $this->db->prepare("SELECT COALESCE(MAX(version), 0) + 1 AS v FROM receta_produccion WHERE id_producto = :id_producto");
            $verStmt->execute(['id_producto' => $idProducto]);
            $vrow = $verStmt->fetch(PDO::FETCH_ASSOC);
            $version = (int)($vrow['v'] ?? 1);

            $result = $stmtRec->execute([
                'id_producto' => $idProducto,
                'fecha_creacion' => date('Y-m-d H:i:s'),
                'creado_por' => $usuarioId,
                'version' => $version,
            ]);

            if (!$result) {
                throw new \Exception('No se pudo crear nueva receta: ' . implode(', ', $stmtRec->errorInfo()));
            }

            $idReceta = (int)$this->db->lastInsertId();
            error_log("ProductoRepository::updateWithReceta - Nueva receta creada con ID: $idReceta, versión: $version");

            $detailQuery = "INSERT INTO receta_detalle (id_receta, id_insumo, cantidad, creado_por) VALUES (:id_receta, :id_insumo, :cantidad, :creado_por)";
            $detailStmt = $this->db->prepare($detailQuery);

            foreach ($detalles as $d) {
                $idInsumo = (int)($d['id_insumo'] ?? 0);
                $cantidad = isset($d['cantidad']) ? (float)$d['cantidad'] : 0;
                if ($idInsumo <= 0 || $cantidad <= 0) {
                    error_log("ProductoRepository::updateWithReceta - Saltando detalle: idInsumo=$idInsumo, cantidad=$cantidad");
                    continue;
                }

                $result = $detailStmt->execute([
                    'id_receta' => $idReceta,
                    'id_insumo' => $idInsumo,
                    'cantidad' => $cantidad,
                    'creado_por' => $usuarioId,
                ]);

                if (!$result) {
                    throw new \Exception('No se pudo crear detalles de receta: ' . implode(', ', $detailStmt->errorInfo()));
                }
            }

            error_log("ProductoRepository::updateWithReceta - Todos los detalles insertados, commit de transacción");
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            error_log("ProductoRepository::updateWithReceta - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            try {
                $this->db->rollBack();
            } catch (\Throwable $rollbackError) {
                error_log("ProductoRepository::updateWithReceta - Error en rollback: " . $rollbackError->getMessage());
            }
            throw $e;
        }
    }
}
