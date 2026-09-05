<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Models\Insumo;

class InsumoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    private function hasStockColumn(): bool
    {
        $query = "SHOW COLUMNS FROM insumo LIKE 'stock'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function ensureStockColumn(): void
    {
        if ($this->hasStockColumn()) {
            return;
        }

        $query = "ALTER TABLE insumo ADD COLUMN stock DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER id_unidad";
        $this->db->exec($query);
    }

    private function hasPrecioCompraColumn(): bool
    {
        $query = "SHOW COLUMNS FROM insumo LIKE 'precio_compra'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function ensurePrecioCompraColumn(): void
    {
        if ($this->hasPrecioCompraColumn()) {
            return;
        }

        $query = "ALTER TABLE insumo ADD COLUMN precio_compra DECIMAL(12,2) NULL AFTER id_unidad";
        $this->db->exec($query);
    }

    public function all(): array
    {
        $this->ensurePrecioCompraColumn();
        $stockColumn = $this->hasStockColumn() ? 'stock' : '0 AS stock';
        $query = "SELECT id_insumo, nombre_insumo, descripcion, id_tipo_insumo, id_variedad, id_color, id_categoria, id_unidad, precio_compra, {$stockColumn} AS stock, estado, fecha_creacion, creado_por
                  FROM insumo
                  WHERE estado = 1
                  ORDER BY nombre_insumo ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($data) => new Insumo($data), $rows);
    }

    public function findById(int $id): ?Insumo
    {
        $this->ensurePrecioCompraColumn();
        $stockColumn = $this->hasStockColumn() ? 'stock' : '0 AS stock';
        $query = "SELECT id_insumo, nombre_insumo, descripcion, id_tipo_insumo, id_variedad, id_color, id_categoria, id_unidad, precio_compra, {$stockColumn} AS stock, estado, fecha_creacion, creado_por
                  FROM insumo
                  WHERE id_insumo = :id AND estado = 1
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? new Insumo($data) : null;
    }

    public function incrementarStock(int $idInsumo, float $cantidad): bool
    {
        $this->ensureStockColumn();

        $query = "UPDATE insumo SET stock = stock + :cantidad WHERE id_insumo = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_STR);
        $stmt->bindParam(':id', $idInsumo, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getStockDisponible(int $idInsumo): float
    {
        $this->ensureStockColumn();
        $stock = $this->findById($idInsumo);
        if (!$stock) {
            return 0.0;
        }

        $reservado = $this->getStockReservado($idInsumo);
        return round((float)$stock->stock - $reservado, 2);
    }

    public function getStockReservado(int $idInsumo): float
    {
        if (!$this->tableExists('stock_reserva')) {
            return 0.0;
        }

        $query = "SELECT COALESCE(SUM(cantidad), 0) AS reservado FROM stock_reserva WHERE id_insumo = :id AND estado = 'Reservado'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();
        return (float)$stmt->fetchColumn();
    }

    public function reservarStock(int $idInsumo, float $cantidad, int $pedidoId, int $usuarioId): bool
    {
        if ($cantidad <= 0) {
            return true;
        }

        if (!$this->tableExists('stock_reserva')) {
            return $this->getStockDisponible($idInsumo) >= $cantidad;
        }

        if ($this->getStockDisponible($idInsumo) < $cantidad) {
            return false;
        }

        $query = "INSERT INTO stock_reserva (id_insumo, id_pedido, cantidad, estado, fecha_creacion, creado_por) VALUES (:id_insumo, :id_pedido, :cantidad, 'Reservado', NOW(), :creado_por)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_insumo', $idInsumo, PDO::PARAM_INT);
        $stmt->bindParam(':id_pedido', $pedidoId, PDO::PARAM_INT);
        $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_STR);
        $stmt->bindParam(':creado_por', $usuarioId, $usuarioId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        return $stmt->execute();
    }

    public function consumirReservaPorPedido(int $pedidoId): bool
    {
        if (!$this->tableExists('stock_reserva')) {
            return true;
        }

        $query = "UPDATE stock_reserva SET estado = 'Consumido' WHERE id_pedido = :id_pedido AND estado = 'Reservado'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_pedido', $pedidoId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function liberarReservaPorPedido(int $pedidoId): bool
    {
        if (!$this->tableExists('stock_reserva')) {
            return true;
        }

        $query = "UPDATE stock_reserva SET estado = 'Cancelado' WHERE id_pedido = :id_pedido AND estado = 'Reservado'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_pedido', $pedidoId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function decrementarStock(int $idInsumo, float $cantidad): bool
    {
        $this->ensureStockColumn();

        if ($this->getStockDisponible($idInsumo) < $cantidad) {
            return false;
        }

        $query = "UPDATE insumo SET stock = stock - :cantidad WHERE id_insumo = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_STR);
        $stmt->bindParam(':id', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    public function registrarMovimientoInsumo(int $idInsumo, string $tipoMovimiento, float $cantidad, ?int $idCompra = null, ?int $idOrdenProduccion = null, ?string $motivo = null, ?int $usuarioId = null): bool
    {
        $query = "INSERT INTO movimiento_stock_insumo (id_insumo, tipo_movimiento, id_compra, id_orden_produccion, motivo, cantidad, fecha_movimiento, creado_por) VALUES (:id_insumo, :tipo_movimiento, :id_compra, :id_orden_produccion, :motivo, :cantidad, NOW(), :creado_por)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_insumo', $idInsumo, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_movimiento', $tipoMovimiento, PDO::PARAM_STR);
        $stmt->bindValue(':id_compra', $idCompra, $idCompra !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':id_orden_produccion', $idOrdenProduccion, $idOrdenProduccion !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':motivo', $motivo, $motivo !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_STR);
        $stmt->bindValue(':creado_por', $usuarioId, $usuarioId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        return $stmt->execute();
    }

    public function registrarMovimientoProducto(int $idProducto, float $cantidad, float $precioUnitario, string $tipoMovimiento, ?int $usuarioId = null, ?string $motivo = null): bool
    {
        $query = "INSERT INTO movimiento_stock_producto (id_producto, stock_anterior, tipo_movimiento, cantidad, precio_unitario, fecha_movimiento, creado_por) VALUES (:id_producto, 0, :tipo_movimiento, :cantidad, :precio_unitario, NOW(), :creado_por)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_movimiento', $tipoMovimiento, PDO::PARAM_STR);
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_STR);
        $stmt->bindValue(':precio_unitario', $precioUnitario, PDO::PARAM_STR);
        $stmt->bindValue(':creado_por', $usuarioId, $usuarioId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        return $stmt->execute();
    }

    public function validarUnidadMedida(int $idInsumo): void
    {
        $insumo = $this->findById($idInsumo);
        if (!$insumo) {
            throw new \Exception('No se encontró el insumo asociado con ID: ' . $idInsumo);
        }

        if ((int)$insumo->id_unidad <= 0) {
            throw new \Exception('El insumo "' . $insumo->nombre_insumo . '" debe tener una unidad de medida válida asignada');
        }

        $unidad = $this->obtenerNombreUnidad((int)$insumo->id_unidad);
        if ($unidad === null || $unidad === '') {
            throw new \Exception('La unidad con ID ' . $insumo->id_unidad . ' no existe en el catálogo de unidades');
        }

        $unidadNormalizada = strtolower(trim((string)$unidad));
        $unidadesValidas = ['kg', 'g', 'unidad', 'unidades', 'u', 'ml', 'lt', 'litro', 'litros', 'metro', 'metros', 'metro cuadrado', 'docena', 'dozen', 'caja', 'paquete'];

        if (!in_array($unidadNormalizada, $unidadesValidas, true)) {
            error_log("InsumoRepository::validarUnidadMedida - Unidad inválida para insumo ID $idInsumo: '$unidadNormalizada' (original: '$unidad')");
            throw new \Exception('La unidad de medida "' . $unidad . '" del insumo "' . $insumo->nombre_insumo . '" no es válida. Unidades válidas: ' . implode(', ', $unidadesValidas));
        }
    }

    public function obtenerNombreUnidad(int $idUnidad): ?string
    {
        $query = "SELECT nombre_unidad FROM unidad WHERE id_unidad = :id_unidad LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_unidad', $idUnidad, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['nombre_unidad'] ?? null;
    }

    private function tableExists(string $table): bool
    {
        $safeTable = str_replace(['`', ';', '--', '/*', '*/'], '', $table);
        $query = sprintf("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '%s'", addslashes($safeTable));
        $stmt = $this->db->query($query);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function actualizarPrecioCompra(int $idInsumo, float $precioCompra): bool
    {
        $this->ensurePrecioCompraColumn();

        $query = "UPDATE insumo SET precio_compra = :precio_compra WHERE id_insumo = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':precio_compra', $precioCompra, PDO::PARAM_STR);
        $stmt->bindParam(':id', $idInsumo, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function create(Insumo $insumo): int
    {
        $this->ensurePrecioCompraColumn();
        $query = "INSERT INTO insumo (nombre_insumo, descripcion, id_tipo_insumo, id_variedad, id_color, id_categoria, id_unidad, precio_compra, estado, creado_por)
                  VALUES (:nombre, :descripcion, :id_tipo, :id_variedad, :id_color, :id_categoria, :id_unidad, :precio_compra, :estado, :creado_por)";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nombre', $insumo->nombre_insumo, PDO::PARAM_STR);
        $stmt->bindParam(':descripcion', $insumo->descripcion, PDO::PARAM_STR);
        $stmt->bindParam(':id_tipo', $insumo->id_tipo_insumo, PDO::PARAM_INT);
        $stmt->bindParam(':id_variedad', $insumo->id_variedad, PDO::PARAM_INT);
        $stmt->bindParam(':id_color', $insumo->id_color, PDO::PARAM_INT);
        $stmt->bindParam(':id_categoria', $insumo->id_categoria, PDO::PARAM_INT);
        $stmt->bindParam(':id_unidad', $insumo->id_unidad, PDO::PARAM_INT);
        $stmt->bindValue(':precio_compra', $insumo->precio_compra, PDO::PARAM_STR);
        $stmt->bindParam(':estado', $insumo->estado, PDO::PARAM_INT);
        $stmt->bindParam(':creado_por', $insumo->creado_por, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return (int) $this->db->lastInsertId();
        }

        throw new \Exception('No se pudo crear el insumo');
    }

    public function update(Insumo $insumo): bool
    {
        $this->ensurePrecioCompraColumn();
        $query = "UPDATE insumo SET nombre_insumo = :nombre, descripcion = :descripcion, id_tipo_insumo = :id_tipo, 
                  id_variedad = :id_variedad, id_color = :id_color, id_categoria = :id_categoria, id_unidad = :id_unidad, 
                  precio_compra = :precio_compra, estado = :estado WHERE id_insumo = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $insumo->id_insumo, PDO::PARAM_INT);
        $stmt->bindParam(':nombre', $insumo->nombre_insumo, PDO::PARAM_STR);
        $stmt->bindParam(':descripcion', $insumo->descripcion, PDO::PARAM_STR);
        $stmt->bindParam(':id_tipo', $insumo->id_tipo_insumo, PDO::PARAM_INT);
        $stmt->bindParam(':id_variedad', $insumo->id_variedad, PDO::PARAM_INT);
        $stmt->bindParam(':id_color', $insumo->id_color, PDO::PARAM_INT);
        $stmt->bindParam(':id_categoria', $insumo->id_categoria, PDO::PARAM_INT);
        $stmt->bindParam(':id_unidad', $insumo->id_unidad, PDO::PARAM_INT);
        $stmt->bindValue(':precio_compra', $insumo->precio_compra, PDO::PARAM_STR);
        $stmt->bindParam(':estado', $insumo->estado, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete(int $id, bool $hardDelete = false): bool
    {
        if ($hardDelete) {
            $query = "DELETE FROM insumo WHERE id_insumo = :id";
        } else {
            $query = "UPDATE insumo SET estado = 0 WHERE id_insumo = :id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
