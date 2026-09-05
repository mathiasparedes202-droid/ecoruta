<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use App\Repositories\ProductoRepository;
use App\Repositories\InsumoRepository;

class PedidoRepository
{
    private PDO $db;
    private ProductoRepository $productoRepo;
    private InsumoRepository $insumoRepo;

    public function __construct()
    {
        $this->db = (new Database())->connect();
        $this->productoRepo = new ProductoRepository();
        $this->insumoRepo = new InsumoRepository();
    }

    public function listProductionOrders(): array
    {
        $selectFields = [
            'p.id_pedido',
            'p.id_cliente',
            'COALESCE(c.razon_social, p.nombre_cliente_manual) AS cliente',
            'c.ruc_ci AS ruc_ci',
            'p.fecha_entrega',
            'p.fecha_creacion',
            'p.tipo_entrega',
            'p.estado AS estado_pedido',
            'p.costo_estimado',
            'p.precio_final',
            'p.observaciones',
            'COALESCE((SELECT COUNT(*) FROM pedido_detalle pd WHERE pd.id_pedido = p.id_pedido), 0) AS total_items',
            'op.id_orden',
            'op.estado AS estado_produccion',
        ];

        if ($this->columnExists('pedido', 'mensaje_personalizado')) {
            $selectFields[] = 'p.mensaje_personalizado';
        }
        if ($this->columnExists('pedido', 'presentacion')) {
            $selectFields[] = 'p.presentacion';
        }
        if ($this->columnExists('pedido', 'detalle_personalizacion')) {
            $selectFields[] = 'p.detalle_personalizacion';
        }
        if ($this->columnExists('pedido', 'repartidor')) {
            $selectFields[] = 'p.repartidor';
        }
        if ($this->columnExists('pedido', 'estado_entrega')) {
            $selectFields[] = 'p.estado_entrega';
        }
        if ($this->columnExists('pedido', 'fecha_hora_entrega')) {
            $selectFields[] = 'p.fecha_hora_entrega';
        }
        if ($this->columnExists('pedido', 'etapas_produccion')) {
            $selectFields[] = 'p.etapas_produccion';
        }
        if ($this->columnExists('pedido', 'progreso_produccion')) {
            $selectFields[] = 'p.progreso_produccion';
        }

        $query = sprintf("SELECT %s
                  FROM pedido p
                  JOIN orden_produccion op ON p.id_pedido = op.id_pedido
                  LEFT JOIN cliente c ON p.id_cliente = c.id_cliente
                  WHERE op.estado_logico = 1
                  ORDER BY p.fecha_creacion DESC", implode(",\n                         ", $selectFields));

        $stmt = $this->db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findProductionOrderById(int $id): ?array
    {
        $selectFields = [
            'p.id_pedido',
            'p.id_cliente',
            'COALESCE(c.razon_social, p.nombre_cliente_manual) AS cliente',
            'c.ruc_ci AS ruc_ci',
            'p.nombre_cliente_manual',
            'p.telefono_cliente',
            'p.fecha_entrega',
            'p.fecha_creacion',
            'p.tipo_entrega',
            'p.estado AS estado_pedido',
            'p.costo_estimado',
            'p.precio_final',
            'p.observaciones',
            'COALESCE((SELECT COUNT(*) FROM pedido_detalle pd WHERE pd.id_pedido = p.id_pedido), 0) AS total_items',
            'op.id_orden',
            'op.estado AS estado_produccion',
        ];

        if ($this->columnExists('pedido', 'mensaje_personalizado')) {
            $selectFields[] = 'p.mensaje_personalizado';
        }
        if ($this->columnExists('pedido', 'presentacion')) {
            $selectFields[] = 'p.presentacion';
        }
        if ($this->columnExists('pedido', 'detalle_personalizacion')) {
            $selectFields[] = 'p.detalle_personalizacion';
        }
        if ($this->columnExists('pedido', 'repartidor')) {
            $selectFields[] = 'p.repartidor';
        }
        if ($this->columnExists('pedido', 'estado_entrega')) {
            $selectFields[] = 'p.estado_entrega';
        }
        if ($this->columnExists('pedido', 'fecha_hora_entrega')) {
            $selectFields[] = 'p.fecha_hora_entrega';
        }
        if ($this->columnExists('pedido', 'etapas_produccion')) {
            $selectFields[] = 'p.etapas_produccion';
        }
        if ($this->columnExists('pedido', 'progreso_produccion')) {
            $selectFields[] = 'p.progreso_produccion';
        }

        $query = sprintf("SELECT %s
                  FROM pedido p
                  JOIN orden_produccion op ON p.id_pedido = op.id_pedido
                  LEFT JOIN cliente c ON p.id_cliente = c.id_cliente
                  WHERE p.id_pedido = :id_pedido
                    AND op.estado_logico = 1
                  LIMIT 1", implode(",\n                         ", $selectFields));

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_pedido', $id, PDO::PARAM_INT);
        $stmt->execute();
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            return null;
        }

        $selectFields = [
            'pd.id_pedido_detalle',
            'pd.id_producto',
            'pd.cantidad',
            'pd.precio_estimado',
            'pr.nombre_producto',
            'pr.descripcion',
            'pr.tipo_producto',
            'pr.precio_base',
            'pr.costo_produccion',
        ];

        if ($this->columnExists('pedido_detalle', 'detalle_receta')) {
            $selectFields[] = 'pd.detalle_receta';
        }

        $itemsQuery = sprintf("SELECT %s
                        FROM pedido_detalle pd
                        JOIN producto pr ON pd.id_producto = pr.id_producto
                        WHERE pd.id_pedido = :id_pedido
                        ORDER BY pd.id_pedido_detalle", implode(",\n                               ", $selectFields));

        $itemsStmt = $this->db->prepare($itemsQuery);
        $itemsStmt->bindParam(':id_pedido', $id, PDO::PARAM_INT);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $pedido['detalles'] = array_map(function ($row) {
            if (array_key_exists('detalle_receta', $row)) {
                $row['detalle_receta'] = !empty($row['detalle_receta']) ? json_decode($row['detalle_receta'], true) : [];
            } else {
                $row['detalle_receta'] = [];
            }
            return $row;
        }, $items);

        return $pedido;
    }

    public function createProductionOrder(array $data, int $usuarioId): int
    {
        $detalles = $data['detalles'] ?? [];

        if (!is_array($detalles) || count($detalles) === 0) {
            throw new \InvalidArgumentException('Debe agregar al menos un producto a la orden de producción.');
        }

        $nombreClienteManual = trim($data['nombre_cliente_manual'] ?? '');
        $idCliente = !empty($data['id_cliente']) ? (int)$data['id_cliente'] : null;

        if (!$idCliente && $nombreClienteManual === '') {
            throw new \InvalidArgumentException('Debe proporcionar un cliente o un nombre de cliente manual.');
        }

        $tipoEntregaValue = $data['tipo_entrega'] ?? 'Delivery';
        $tipoEntrega = in_array($tipoEntregaValue, ['Delivery', 'Retiro']) ? $tipoEntregaValue : 'Delivery';
        $franjaHoraria = in_array($data['franja_horaria'] ?? '', ['Mañana', 'Tarde']) ? ($data['franja_horaria'] ?? null) : null;
        $fechaEntrega = !empty($data['fecha_entrega']) ? $data['fecha_entrega'] : null;
        $direccionEntrega = trim($data['direccion_entrega'] ?? '');
        $observaciones = trim($data['observaciones'] ?? '');
        $mensajePersonalizado = trim((string)($data['mensaje_personalizado'] ?? ''));
        $presentacion = trim((string)($data['presentacion'] ?? ''));
        $detallePersonalizacion = $data['detalle_personalizacion'] ?? [
            'flores' => trim((string)($data['flores'] ?? '')),
            'colores' => trim((string)($data['colores'] ?? '')),
            'accesorios' => trim((string)($data['accesorios'] ?? '')),
        ];
        $detallePersonalizacionJson = is_array($detallePersonalizacion) || is_object($detallePersonalizacion)
            ? json_encode($detallePersonalizacion, JSON_UNESCAPED_UNICODE)
            : (string)$detallePersonalizacion;
        $etapasProduccion = is_array($data['etapas_produccion'] ?? null) ? json_encode($data['etapas_produccion'], JSON_UNESCAPED_UNICODE) : null;
        $progresoProduccion = isset($data['progreso_produccion']) ? (int)$data['progreso_produccion'] : 0;
        $repartidor = trim((string)($data['repartidor'] ?? ''));
        $estadoEntrega = trim((string)($data['estado_entrega'] ?? 'Pendiente'));
        $fechaHoraEntrega = !empty($data['fecha_hora_entrega']) ? $data['fecha_hora_entrega'] : null;

        $costoEstimado = 0.0;
        $precioFinal = 0.0;
        $pedidoEstado = $this->normalizePedidoEstado($data['estado'] ?? null);
        $ordenEstado = $this->normalizeOrdenEstado($data['orden_estado'] ?? null);
        $progresoProduccion = isset($data['progreso_produccion']) ? (int)$data['progreso_produccion'] : 0;

        if (!in_array($pedidoEstado, ['Pendiente', 'En proceso', 'Terminado', 'Entregado', 'Cancelado'], true)) {
            throw new \InvalidArgumentException('El estado del pedido no es valido');
        }
        if (!in_array($ordenEstado, ['Pendiente', 'En proceso', 'Finalizado', 'Cancelado'], true)) {
            throw new \InvalidArgumentException('El estado de produccion no es valido');
        }
        if ($progresoProduccion < 0 || $progresoProduccion > 100) {
            throw new \InvalidArgumentException('El progreso de produccion debe estar entre 0 y 100');
        }

        foreach ($detalles as $item) {
            $nombreProducto = trim((string)($item['nombre_producto'] ?? ''));
            if (empty($item['id_producto']) && $nombreProducto === '') {
                throw new \InvalidArgumentException('Cada elemento de producción debe tener un producto seleccionado o un nombre de producto.');
            }

            $cantidad = max(1, (int)($item['cantidad'] ?? 1));
            $idProducto = !empty($item['id_producto']) ? (int)$item['id_producto'] : null;
            $precioEstimado = isset($item['precio_estimado']) ? (float)$item['precio_estimado'] : 0.0;

            if ($cantidad <= 0) {
                throw new \InvalidArgumentException('La cantidad de cada producto debe ser mayor a 0');
            }

            if ($idProducto !== null && $idProducto > 0) {
                $this->validarStockRecetaParaProducto($idProducto, $cantidad);
                $pricing = $this->productoRepo->getCostoYPrecioVenta($idProducto);
                if ((float)$pricing['precio_venta'] <= 0) {
                    throw new \InvalidArgumentException('El producto no tiene un precio calculado');
                }
                $costoEstimado += (float)$pricing['costo_produccion'] * $cantidad;
                $precioFinal += (float)$pricing['precio_venta'] * $cantidad;
            } else {
                $costoEstimado += $precioEstimado * $cantidad;
                $precioFinal += $precioEstimado * $cantidad;
            }
        }

        try {
            $this->db->beginTransaction();

            $pedidoColumns = ['id_cliente', 'nombre_cliente_manual', 'telefono_cliente', 'fecha_entrega', 'franja_horaria', 'tipo_entrega', 'direccion_entrega', 'costo_estimado', 'precio_final', 'estado', 'observaciones'];
            $pedidoValues = [':id_cliente', ':nombre_cliente_manual', ':telefono_cliente', ':fecha_entrega', ':franja_horaria', ':tipo_entrega', ':direccion_entrega', ':costo_estimado', ':precio_final', ':estado', ':observaciones'];

            if ($this->columnExists('pedido', 'mensaje_personalizado')) {
                $pedidoColumns[] = 'mensaje_personalizado';
                $pedidoValues[] = ':mensaje_personalizado';
            }
            if ($this->columnExists('pedido', 'presentacion')) {
                $pedidoColumns[] = 'presentacion';
                $pedidoValues[] = ':presentacion';
            }
            if ($this->columnExists('pedido', 'detalle_personalizacion')) {
                $pedidoColumns[] = 'detalle_personalizacion';
                $pedidoValues[] = ':detalle_personalizacion';
            }
            if ($this->columnExists('pedido', 'repartidor')) {
                $pedidoColumns[] = 'repartidor';
                $pedidoValues[] = ':repartidor';
            }
            if ($this->columnExists('pedido', 'estado_entrega')) {
                $pedidoColumns[] = 'estado_entrega';
                $pedidoValues[] = ':estado_entrega';
            }
            if ($this->columnExists('pedido', 'fecha_hora_entrega')) {
                $pedidoColumns[] = 'fecha_hora_entrega';
                $pedidoValues[] = ':fecha_hora_entrega';
            }
            if ($this->columnExists('pedido', 'etapas_produccion')) {
                $pedidoColumns[] = 'etapas_produccion';
                $pedidoValues[] = ':etapas_produccion';
            }
            if ($this->columnExists('pedido', 'progreso_produccion')) {
                $pedidoColumns[] = 'progreso_produccion';
                $pedidoValues[] = ':progreso_produccion';
            }
            $pedidoColumns[] = 'creado_por';
            $pedidoValues[] = ':creado_por';

            $pedidoSql = sprintf('INSERT INTO pedido (%s) VALUES (%s)', implode(', ', $pedidoColumns), implode(', ', $pedidoValues));
            $pedidoStmt = $this->db->prepare($pedidoSql);
            $pedidoStmt->bindValue(':id_cliente', $idCliente, $idCliente !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $pedidoStmt->bindValue(':nombre_cliente_manual', $nombreClienteManual !== '' ? $nombreClienteManual : null, $nombreClienteManual !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $pedidoStmt->bindValue(':telefono_cliente', trim($data['telefono_cliente'] ?? '') ?: null, PDO::PARAM_STR);
            $pedidoStmt->bindValue(':fecha_entrega', $fechaEntrega ?: null, PDO::PARAM_STR);
            $pedidoStmt->bindValue(':franja_horaria', $franjaHoraria ?: null, PDO::PARAM_STR);
            $pedidoStmt->bindValue(':tipo_entrega', $tipoEntrega, PDO::PARAM_STR);
            $pedidoStmt->bindValue(':direccion_entrega', $direccionEntrega !== '' ? $direccionEntrega : null, $direccionEntrega !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $pedidoStmt->bindValue(':costo_estimado', $costoEstimado, PDO::PARAM_STR);
            $pedidoStmt->bindValue(':precio_final', $precioFinal, PDO::PARAM_STR);
            $pedidoStmt->bindValue(':estado', $pedidoEstado !== '' ? $pedidoEstado : 'Pendiente', PDO::PARAM_STR);
            $pedidoStmt->bindValue(':observaciones', $observaciones !== '' ? $observaciones : null, $observaciones !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            if ($this->columnExists('pedido', 'mensaje_personalizado')) {
                $pedidoStmt->bindValue(':mensaje_personalizado', $mensajePersonalizado !== '' ? $mensajePersonalizado : null, $mensajePersonalizado !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }
            if ($this->columnExists('pedido', 'presentacion')) {
                $pedidoStmt->bindValue(':presentacion', $presentacion !== '' ? $presentacion : null, $presentacion !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }
            if ($this->columnExists('pedido', 'detalle_personalizacion')) {
                $pedidoStmt->bindValue(':detalle_personalizacion', $detallePersonalizacionJson !== '' ? $detallePersonalizacionJson : null, $detallePersonalizacionJson !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }
            if ($this->columnExists('pedido', 'repartidor')) {
                $pedidoStmt->bindValue(':repartidor', $repartidor !== '' ? $repartidor : null, $repartidor !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }
            if ($this->columnExists('pedido', 'estado_entrega')) {
                $pedidoStmt->bindValue(':estado_entrega', $estadoEntrega !== '' ? $estadoEntrega : 'Pendiente', PDO::PARAM_STR);
            }
            if ($this->columnExists('pedido', 'fecha_hora_entrega')) {
                $pedidoStmt->bindValue(':fecha_hora_entrega', $fechaHoraEntrega ?: null, PDO::PARAM_STR);
            }
            if ($this->columnExists('pedido', 'etapas_produccion')) {
                $pedidoStmt->bindValue(':etapas_produccion', $etapasProduccion ?: null, $etapasProduccion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }
            if ($this->columnExists('pedido', 'progreso_produccion')) {
                $pedidoStmt->bindValue(':progreso_produccion', $progresoProduccion, PDO::PARAM_INT);
            }
            $pedidoStmt->bindValue(':creado_por', $usuarioId > 0 ? $usuarioId : null, $usuarioId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $pedidoStmt->execute();
            $pedidoId = (int)$this->db->lastInsertId();

            $hasDetalleReceta = $this->columnExists('pedido_detalle', 'detalle_receta');
            $detalleColumns = ['id_pedido', 'id_producto', 'cantidad', 'precio_estimado'];
            $detalleValues = [':id_pedido', ':id_producto', ':cantidad', ':precio_estimado'];

            if ($hasDetalleReceta) {
                $detalleColumns[] = 'detalle_receta';
                $detalleValues[] = ':detalle_receta';
            }

            $detalleColumns[] = 'creado_por';
            $detalleValues[] = ':creado_por';

            $detalleSql = sprintf(
                "INSERT INTO pedido_detalle (%s) VALUES (%s)",
                implode(', ', $detalleColumns),
                implode(', ', $detalleValues)
            );
            $detalleStmt = $this->db->prepare($detalleSql);

            foreach ($detalles as $item) {
                $cantidad = max(1, (int)($item['cantidad'] ?? 1));
                $precioEstimado = isset($item['precio_estimado']) ? (float)$item['precio_estimado'] : 0.0;
                $detalleReceta = !empty($item['receta']) ? json_encode($item['receta'], JSON_UNESCAPED_UNICODE) : null;
                $idProducto = !empty($item['id_producto']) ? (int)$item['id_producto'] : $this->findOrCreateProducto((array)$item, $usuarioId);

                if ($idProducto > 0) {
                    $this->reservarStockPedido($pedidoId, $idProducto, $cantidad, $usuarioId);
                }

                $detalleStmt->bindValue(':id_pedido', $pedidoId, PDO::PARAM_INT);
                $detalleStmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
                $detalleStmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
                $detalleStmt->bindValue(':precio_estimado', $precioEstimado, PDO::PARAM_STR);

                if ($hasDetalleReceta) {
                    $detalleStmt->bindValue(':detalle_receta', $detalleReceta, $detalleReceta !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                }

                $detalleStmt->bindValue(':creado_por', $usuarioId > 0 ? $usuarioId : null, $usuarioId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $detalleStmt->execute();
            }

            $ordenSql = "INSERT INTO orden_produccion (id_pedido, estado, creado_por) VALUES (:id_pedido, :estado, :creado_por)";
            $ordenStmt = $this->db->prepare($ordenSql);
            $ordenStmt->bindValue(':id_pedido', $pedidoId, PDO::PARAM_INT);
            $ordenStmt->bindValue(':estado', $ordenEstado !== '' ? $ordenEstado : 'Pendiente', PDO::PARAM_STR);
            $ordenStmt->bindValue(':creado_por', $usuarioId > 0 ? $usuarioId : null, $usuarioId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $ordenStmt->execute();

            $this->db->commit();
        } catch (\InvalidArgumentException $e) {
            error_log("PedidoRepository::createProductionOrder - Validation Error: " . $e->getMessage());
            try {
                $this->db->rollBack();
            } catch (\Throwable $rollbackError) {
                error_log("PedidoRepository::createProductionOrder - Error en rollback: " . $rollbackError->getMessage());
            }
            throw $e;
        } catch (\Exception $e) {
            error_log("PedidoRepository::createProductionOrder - Exception Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            try {
                $this->db->rollBack();
            } catch (\Throwable $rollbackError) {
                error_log("PedidoRepository::createProductionOrder - Error en rollback: " . $rollbackError->getMessage());
            }
            throw $e;
        }

        return $pedidoId;
    }

    public function findOrdenProduccionIdByPedidoId(int $pedidoId): ?int
    {
        $query = "SELECT id_orden FROM orden_produccion WHERE id_pedido = :id_pedido LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_pedido', $pedidoId, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? (int)$data['id_orden'] : null;
    }

    private function normalizePedidoEstado($value): string
    {
        $estado = trim((string)($value ?? ''));
        $key = strtolower($estado);

        switch ($key) {
            case 'completado':
            case 'terminado':
            case 'finalizado':
            case 'listo':
            case 'entregado':
                return 'Terminado';
            case 'cancelado':
            case 'anulado':
            case 'cancel':
                return 'Cancelado';
            case 'en proceso':
            case 'proceso':
            case 'producción':
            case 'en curso':
            case 'fabricando':
            case 'fabricacion':
                return 'En proceso';
            case '':
                return 'Pendiente';
            default:
                return $estado !== '' ? $estado : 'Pendiente';
        }
    }

    private function normalizeOrdenEstado($value): string
    {
        $estado = trim((string)($value ?? ''));
        $key = strtolower($estado);

        switch ($key) {
            case 'completado':
            case 'terminado':
            case 'finalizado':
            case 'listo':
            case 'entregado':
                return 'Finalizado';
            case 'cancelado':
            case 'anulado':
            case 'cancel':
                return 'Cancelado';
            case 'en proceso':
            case 'proceso':
            case 'producción':
            case 'en curso':
            case 'fabricando':
            case 'fabricacion':
                return 'En proceso';
            case '':
                return 'Pendiente';
            default:
                return $estado !== '' ? $estado : 'Pendiente';
        }
    }

    private function validarStockRecetaParaProducto(int $idProducto, float $cantidad): void
    {
        error_log("PedidoRepository::validarStockRecetaParaProducto - Validando producto ID: $idProducto con cantidad: $cantidad");

        $receta = $this->productoRepo->findActiveRecipeByProducto($idProducto);
        if (!$receta) {
            throw new \InvalidArgumentException('El producto no tiene una receta activa para producir');
        }

        $detalles = $this->productoRepo->findRecipeDetails((int)$receta['id_receta']);
        if (empty($detalles)) {
            throw new \InvalidArgumentException('La receta del producto no tiene insumos asociados');
        }

        error_log("PedidoRepository::validarStockRecetaParaProducto - Receta ID: {$receta['id_receta']} con " . count($detalles) . " detalles");

        foreach ($detalles as $detalle) {
            $idInsumo = (int)($detalle['id_insumo'] ?? 0);
            $cantidadNecesaria = (float)($detalle['cantidad'] ?? 0) * $cantidad;
            if ($idInsumo <= 0 || $cantidadNecesaria <= 0) {
                error_log("PedidoRepository::validarStockRecetaParaProducto - Saltando detalle: idInsumo=$idInsumo, cantidadNecesaria=$cantidadNecesaria");
                continue;
            }

            error_log("PedidoRepository::validarStockRecetaParaProducto - Validando unidad de insumo ID: $idInsumo");
            try {
                $this->insumoRepo->validarUnidadMedida($idInsumo);
            } catch (\Exception $e) {
                error_log("PedidoRepository::validarStockRecetaParaProducto - Error validando unidad: " . $e->getMessage());
                throw $e;
            }

            $insumo = $this->insumoRepo->findById($idInsumo);
            if (!$insumo) {
                throw new \InvalidArgumentException('No se encontró un insumo requerido para la receta de producción');
            }

            $stockDisponible = $this->insumoRepo->getStockDisponible($idInsumo);
            error_log("PedidoRepository::validarStockRecetaParaProducto - Insumo: {$insumo->nombre_insumo}, Stock: $stockDisponible, Necesario: $cantidadNecesaria");

            if ($stockDisponible < $cantidadNecesaria) {
                throw new \InvalidArgumentException('Stock insuficiente para iniciar la orden de producción. Insumo: ' . $insumo->nombre_insumo);
            }
        }

        error_log("PedidoRepository::validarStockRecetaParaProducto - Validación exitosa para producto ID: $idProducto");
    }

    private function reservarStockPedido(int $pedidoId, int $idProducto, float $cantidad, int $usuarioId): void
    {
        $receta = $this->productoRepo->findActiveRecipeByProducto($idProducto);
        if (!$receta) {
            throw new \InvalidArgumentException('El producto no tiene una receta activa para producir');
        }

        $detalles = $this->productoRepo->findRecipeDetails((int)$receta['id_receta']);
        if (empty($detalles)) {
            throw new \InvalidArgumentException('La receta del producto no tiene insumos asociados');
        }

        foreach ($detalles as $detalle) {
            $idInsumo = (int)($detalle['id_insumo'] ?? 0);
            $cantidadNecesaria = (float)($detalle['cantidad'] ?? 0) * $cantidad;
            if ($idInsumo <= 0 || $cantidadNecesaria <= 0) {
                continue;
            }

            if (!$this->insumoRepo->reservarStock($idInsumo, $cantidadNecesaria, $pedidoId, $usuarioId)) {
                throw new \InvalidArgumentException('No se pudo reservar stock para la orden de producción');
            }
        }
    }

    private function liberarReservaPedido(int $pedidoId): void
    {
        $this->insumoRepo->liberarReservaPorPedido($pedidoId);
    }

    private function findOrCreateProducto(array $item, int $usuarioId): int
    {
        $nombreProducto = trim($item['nombre_producto'] ?? '');
        if ($nombreProducto === '') {
            throw new \InvalidArgumentException('Cada elemento de producción debe tener un nombre de producto si no selecciona uno existente.');
        }

        $producto = $this->productoRepo->findByName($nombreProducto);
        if ($producto) {
            return $producto->id_producto;
        }

        $tipoProducto = trim($item['tipo_producto'] ?? '') ?: 'Ramo';
        $descripcion = trim($item['descripcion'] ?? '');
        $precioBase = isset($item['precio_estimado']) ? (float)$item['precio_estimado'] : 0.0;
        $costoProduccion = isset($item['costo_produccion']) ? (float)$item['costo_produccion'] : $precioBase;

        return $this->productoRepo->create([
            'nombre_producto' => $nombreProducto,
            'tipo_producto' => $tipoProducto,
            'descripcion' => $descripcion,
            'precio_base' => $precioBase,
            'costo_produccion' => $costoProduccion,
            'estado' => 1,
            'es_personalizable' => 0,
            'creado_por' => $usuarioId > 0 ? $usuarioId : null,
        ]);
    }

    public function updateProductionOrder(int $id, array $data): bool
    {
        try {
            $this->db->beginTransaction();

            // Actualizar estado de la orden de producción
            if (isset($data['estado'])) {
                $estado = $this->normalizeOrdenEstado($data['estado']);
                if (!in_array($estado, ['Pendiente', 'En proceso', 'Finalizado', 'Cancelado'], true)) {
                    throw new \InvalidArgumentException('El estado de produccion no es valido');
                }
                $estadoSql = "UPDATE orden_produccion SET estado = :estado WHERE id_pedido = :id_pedido";
                $estadoStmt = $this->db->prepare($estadoSql);
                $estadoStmt->bindValue(':estado', $estado, PDO::PARAM_STR);
                $estadoStmt->bindValue(':id_pedido', $id, PDO::PARAM_INT);
                $estadoStmt->execute();

                if ($estado === 'Finalizado') {
                    $this->consumirRecetaOrden($id);
                }

                if ($estado === 'Cancelado') {
                    $this->liberarReservaPedido($id);
                }

                $pedidoEstado = $estado === 'Finalizado' ? 'Terminado' : ($estado === 'En proceso' ? 'En proceso' : ($estado === 'Cancelado' ? 'Cancelado' : 'Pendiente'));
                $pedidoEstadoStmt = $this->db->prepare('UPDATE pedido SET estado = :estado WHERE id_pedido = :id_pedido');
                $pedidoEstadoStmt->execute(['estado' => $pedidoEstado, 'id_pedido' => $id]);
            }

            // Actualizar datos del pedido
            $updateFields = [];
            $params = [':id_pedido' => $id];

            if (isset($data['nombre_cliente_manual'])) {
                $updateFields[] = "nombre_cliente_manual = :nombre_cliente_manual";
                $params[':nombre_cliente_manual'] = $data['nombre_cliente_manual'];
            }

            if (isset($data['fecha_entrega'])) {
                $updateFields[] = "fecha_entrega = :fecha_entrega";
                $params[':fecha_entrega'] = $data['fecha_entrega'];
            }

            if (isset($data['observaciones'])) {
                $updateFields[] = "observaciones = :observaciones";
                $params[':observaciones'] = $data['observaciones'];
            }

            if (array_key_exists('mensaje_personalizado', $data) && $this->columnExists('pedido', 'mensaje_personalizado')) {
                $updateFields[] = "mensaje_personalizado = :mensaje_personalizado";
                $params[':mensaje_personalizado'] = $data['mensaje_personalizado'];
            }

            if (array_key_exists('presentacion', $data) && $this->columnExists('pedido', 'presentacion')) {
                $updateFields[] = "presentacion = :presentacion";
                $params[':presentacion'] = $data['presentacion'];
            }

            if (array_key_exists('detalle_personalizacion', $data) && $this->columnExists('pedido', 'detalle_personalizacion')) {
                $updateFields[] = "detalle_personalizacion = :detalle_personalizacion";
                $params[':detalle_personalizacion'] = is_array($data['detalle_personalizacion']) || is_object($data['detalle_personalizacion']) ? json_encode($data['detalle_personalizacion'], JSON_UNESCAPED_UNICODE) : (string)$data['detalle_personalizacion'];
            }

            if (array_key_exists('repartidor', $data) && $this->columnExists('pedido', 'repartidor')) {
                $updateFields[] = "repartidor = :repartidor";
                $params[':repartidor'] = $data['repartidor'];
            }

            if (array_key_exists('estado_entrega', $data) && $this->columnExists('pedido', 'estado_entrega')) {
                $updateFields[] = "estado_entrega = :estado_entrega";
                $params[':estado_entrega'] = $data['estado_entrega'];
            }

            if (array_key_exists('fecha_hora_entrega', $data) && $this->columnExists('pedido', 'fecha_hora_entrega')) {
                $updateFields[] = "fecha_hora_entrega = :fecha_hora_entrega";
                $params[':fecha_hora_entrega'] = $data['fecha_hora_entrega'];
            }

            if (array_key_exists('etapas_produccion', $data) && $this->columnExists('pedido', 'etapas_produccion')) {
                $updateFields[] = "etapas_produccion = :etapas_produccion";
                $params[':etapas_produccion'] = is_array($data['etapas_produccion']) ? json_encode($data['etapas_produccion'], JSON_UNESCAPED_UNICODE) : $data['etapas_produccion'];
            }

            if (array_key_exists('progreso_produccion', $data) && $this->columnExists('pedido', 'progreso_produccion')) {
                if ((int)$data['progreso_produccion'] < 0 || (int)$data['progreso_produccion'] > 100) {
                    throw new \InvalidArgumentException('El progreso de produccion debe estar entre 0 y 100');
                }
                $updateFields[] = "progreso_produccion = :progreso_produccion";
                $params[':progreso_produccion'] = (int)$data['progreso_produccion'];
            }

            if (!empty($updateFields)) {
                $updateSql = "UPDATE pedido SET " . implode(', ', $updateFields) . " WHERE id_pedido = :id_pedido";
                $updateStmt = $this->db->prepare($updateSql);

                foreach ($params as $key => $value) {
                    $updateStmt->bindValue($key, $value, PDO::PARAM_STR);
                }

                $updateStmt->execute();
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteProductionOrder(int $id): bool
    {
        try {
            $this->db->beginTransaction();

            // Marcar como eliminado lógicamente
            $sql = "UPDATE orden_produccion SET estado_logico = 0 WHERE id_pedido = :id_pedido";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_pedido', $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Al facturar un pedido, la producción deja de estar activa. Se registra el
     * consumo pendiente y se conserva la orden para auditoría con baja lógica.
     */
    public function closeProductionOrderForSale(int $pedidoId): bool
    {
        try {
            $this->db->beginTransaction();

            $ordenStmt = $this->db->prepare('SELECT id_orden FROM orden_produccion WHERE id_pedido = :id_pedido AND estado_logico = 1 LIMIT 1 FOR UPDATE');
            $ordenStmt->execute(['id_pedido' => $pedidoId]);
            if (!(int)$ordenStmt->fetchColumn()) {
                throw new \InvalidArgumentException('La orden de producción vinculada no está activa');
            }

            $this->consumirRecetaOrden($pedidoId);

            $ordenUpdate = $this->db->prepare("UPDATE orden_produccion SET estado = 'Finalizado', estado_logico = 0, fecha_fin = NOW() WHERE id_pedido = :id_pedido");
            $ordenUpdate->execute(['id_pedido' => $pedidoId]);

            $pedidoFields = ["estado = 'Entregado'"];
            if ($this->columnExists('pedido', 'estado_entrega')) {
                $pedidoFields[] = "estado_entrega = 'Entregado'";
            }
            if ($this->columnExists('pedido', 'fecha_hora_entrega')) {
                $pedidoFields[] = 'fecha_hora_entrega = COALESCE(fecha_hora_entrega, NOW())';
            }
            $pedidoUpdate = $this->db->prepare('UPDATE pedido SET ' . implode(', ', $pedidoFields) . ' WHERE id_pedido = :id_pedido');
            $pedidoUpdate->execute(['id_pedido' => $pedidoId]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Consume la receta una sola vez cuando la orden se finaliza. */
    private function consumirRecetaOrden(int $pedidoId): void
    {
        $ordenStmt = $this->db->prepare('SELECT id_orden FROM orden_produccion WHERE id_pedido = :id_pedido AND estado_logico = 1 LIMIT 1 FOR UPDATE');
        $ordenStmt->execute(['id_pedido' => $pedidoId]);
        $ordenId = (int)$ordenStmt->fetchColumn();
        if ($ordenId <= 0) {
            throw new \InvalidArgumentException('La orden de producción no existe');
        }

        $yaConsumida = $this->db->prepare('SELECT COUNT(*) FROM produccion_consumo WHERE id_orden = :id_orden');
        $yaConsumida->execute(['id_orden' => $ordenId]);
        if ((int)$yaConsumida->fetchColumn() > 0) {
            return;
        }

        $recetaStmt = $this->db->prepare("SELECT rd.id_insumo, SUM(rd.cantidad * pd.cantidad) AS cantidad
            FROM pedido_detalle pd
            JOIN receta_produccion rp ON rp.id_producto = pd.id_producto AND rp.estado = 1
            JOIN receta_detalle rd ON rd.id_receta = rp.id_receta AND rd.estado = 1
            WHERE pd.id_pedido = :id_pedido
            GROUP BY rd.id_insumo");
        $recetaStmt->execute(['id_pedido' => $pedidoId]);
        $consumos = $recetaStmt->fetchAll(PDO::FETCH_ASSOC);

        $insertStmt = $this->db->prepare('INSERT INTO produccion_consumo (id_orden, id_insumo, cantidad_usada) VALUES (:id_orden, :id_insumo, :cantidad)');
        foreach ($consumos as $consumo) {
            $idInsumo = (int)$consumo['id_insumo'];
            $cantidad = (float)$consumo['cantidad'];

            if (!$this->insumoRepo->decrementarStock($idInsumo, $cantidad)) {
                throw new \InvalidArgumentException('Stock insuficiente para finalizar la orden de producción');
            }

            $insertStmt->execute([
                'id_orden' => $ordenId,
                'id_insumo' => $idInsumo,
                'cantidad' => $cantidad,
            ]);
            $this->insumoRepo->registrarMovimientoInsumo($idInsumo, 'Consumo', $cantidad, null, $ordenId, 'Producción', null);
        }

        $this->insumoRepo->consumirReservaPorPedido($pedidoId);
    }

    private function columnExists(string $table, string $column): bool
    {
        $safeTable = str_replace(['`', ';', '--', '/*', '*/'], '', $table);
        $safeColumn = str_replace(['`', ';', '--', '/*', '*/'], '', $column);
        $query = sprintf(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'",
            addslashes($safeTable),
            addslashes($safeColumn)
        );
        $stmt = $this->db->query($query);
        return (int)$stmt->fetchColumn() > 0;
    }
}
