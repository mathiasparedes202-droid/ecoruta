<?php

namespace App\Repositories;

use Config\Database;
use PDO;

class ReporteRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }
    private function bindDateFilters(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    private function buildDateFilter(string $dateField, ?string $desde, ?string $hasta, array &$params, string $prefix = ''): string
    {
        $filter = '';

        if ($desde) {
            $key = ":{$prefix}desde";
            $filter .= " AND DATE({$dateField}) >= {$key}";
            $params[$key] = $desde;
        }

        if ($hasta) {
            $key = ":{$prefix}hasta";
            $filter .= " AND DATE({$dateField}) <= {$key}";
            $params[$key] = $hasta;
        }

        return $filter;
    }

    public function getVentas(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT v.id_venta, v.numero_factura, v.fecha_emision, v.estado_factura,
                       v.condicion_venta, v.total_factura,
                       c.razon_social AS nombre_cliente,
                       c.ruc_ci AS ruc_ci,
                       COUNT(dv.id_detalle_venta) AS items
                FROM venta v
                JOIN cliente c ON v.id_cliente = c.id_cliente
                LEFT JOIN detalle_venta dv ON v.id_venta = dv.id_venta
                WHERE 1=1";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " GROUP BY v.id_venta ORDER BY v.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasDetalle(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT v.id_venta,
                       v.numero_factura,
                       v.fecha_emision,
                       v.estado_factura,
                       v.condicion_venta,
                       c.razon_social AS nombre_cliente,
                       c.ruc_ci AS ruc_ci,
                       COALESCE(p.nombre_producto, i.nombre_insumo) AS nombre_producto,
                       COALESCE(p.tipo_producto, COALESCE(ti.nombre_tipo, 'Insumo')) AS tipo_producto,
                       COALESCE(dv.cantidad, 0) AS cantidad,
                       COALESCE(dv.precio_unitario, 0) AS precio_unitario,
                       COALESCE(dv.subtotal, 0) AS subtotal,
                       dv.detalle_produccion
                FROM venta v
                JOIN cliente c ON v.id_cliente = c.id_cliente
                JOIN detalle_venta dv ON v.id_venta = dv.id_venta
                LEFT JOIN producto p ON dv.id_producto = p.id_producto
                LEFT JOIN insumo i ON dv.id_insumo = i.id_insumo
                LEFT JOIN tipo_insumo ti ON i.id_tipo_insumo = ti.id_tipo_insumo
                WHERE 1=1";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " ORDER BY v.fecha_emision DESC, v.numero_factura DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasPorCliente(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT c.razon_social AS nombre_cliente,
                       c.ruc_ci AS ruc_ci,
                       COUNT(v.id_venta) AS facturas,
                       COALESCE(SUM(v.total_factura), 0) AS total_vendido
                FROM venta v
                JOIN cliente c ON v.id_cliente = c.id_cliente
                WHERE 1=1";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " GROUP BY c.id_cliente ORDER BY total_vendido DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasPorArticulo(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT COALESCE(p.nombre_producto, i.nombre_insumo) AS nombre_producto,
                       COALESCE(p.tipo_producto, COALESCE(ti.nombre_tipo, 'Insumo')) AS tipo_producto,
                       COALESCE(SUM(dv.cantidad), 0) AS cantidad_vendida,
                       COALESCE(SUM(dv.subtotal), 0) AS total_ventas,
                       COUNT(DISTINCT dv.id_venta) AS facturas_vendidas
                FROM detalle_venta dv
                LEFT JOIN producto p ON dv.id_producto = p.id_producto
                LEFT JOIN insumo i ON dv.id_insumo = i.id_insumo
                LEFT JOIN tipo_insumo ti ON i.id_tipo_insumo = ti.id_tipo_insumo
                JOIN venta v ON dv.id_venta = v.id_venta
                WHERE 1=1";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " GROUP BY COALESCE(p.nombre_producto, i.nombre_insumo), COALESCE(p.tipo_producto, COALESCE(ti.nombre_tipo, 'Insumo')) ";
        $sql .= " ORDER BY cantidad_vendida DESC, total_ventas DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasPorCondicion(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT v.condicion_venta,
                       COUNT(v.id_venta) AS facturas,
                       COALESCE(SUM(v.total_factura), 0) AS total_vendido
                FROM venta v
                WHERE 1=1";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " GROUP BY v.condicion_venta ORDER BY total_vendido DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasCreditoPendiente(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT v.id_venta,
                       v.numero_factura,
                       v.fecha_emision,
                       c.razon_social AS nombre_cliente,
                       v.total_factura,
                       COALESCE(SUM(pc.monto_total), 0) AS total_pagado,
                       v.total_factura - COALESCE(SUM(pc.monto_total), 0) AS saldo_pendiente
                FROM venta v
                JOIN cliente c ON v.id_cliente = c.id_cliente
                LEFT JOIN pago_cliente_cabecera pc ON v.id_venta = pc.id_venta AND pc.estado = 'Confirmado'
                WHERE v.condicion_venta = 'Crédito'";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " GROUP BY v.id_venta HAVING total_pagado < v.total_factura ORDER BY v.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVentasCreditoSaldoCero(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT v.id_venta,
                       v.numero_factura,
                       v.fecha_emision,
                       c.razon_social AS nombre_cliente,
                       v.total_factura,
                       COALESCE(SUM(pc.monto_total), 0) AS total_pagado
                FROM venta v
                JOIN cliente c ON v.id_cliente = c.id_cliente
                LEFT JOIN pago_cliente_cabecera pc ON v.id_venta = pc.id_venta AND pc.estado = 'Confirmado'
                WHERE v.condicion_venta = 'Crédito'";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " GROUP BY v.id_venta HAVING total_pagado >= v.total_factura ORDER BY v.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopProductos(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "
            SELECT
                'producto' AS tipo_item,
                p.id_producto AS id_item,
                p.nombre_producto AS nombre_producto,
                COALESCE(p.tipo_producto, 'Producto') AS tipo_producto,
                COALESCE(SUM(dv.cantidad), 0) AS cantidad_vendida,
                COALESCE(SUM(dv.subtotal), 0) AS total_ventas,
                COUNT(DISTINCT dv.id_venta) AS facturas_vendidas
            FROM detalle_venta dv
            JOIN producto p ON dv.id_producto = p.id_producto
            JOIN venta v ON dv.id_venta = v.id_venta
            WHERE dv.id_producto IS NOT NULL AND 1=1";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " GROUP BY p.id_producto";

        $sql .= " UNION ALL SELECT 'insumo' AS tipo_item,
                i.id_insumo AS id_item,
                i.nombre_insumo AS nombre_producto,
                COALESCE(ti.nombre_tipo, 'Insumo') AS tipo_producto,
                COALESCE(SUM(dv.cantidad), 0) AS cantidad_vendida,
                COALESCE(SUM(dv.subtotal), 0) AS total_ventas,
                COUNT(DISTINCT dv.id_venta) AS facturas_vendidas
            FROM detalle_venta dv
            JOIN insumo i ON dv.id_insumo = i.id_insumo
            LEFT JOIN tipo_insumo ti ON i.id_tipo_insumo = ti.id_tipo_insumo
            JOIN venta v ON dv.id_venta = v.id_venta
            WHERE dv.id_insumo IS NOT NULL AND 1=1";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params, 'insumo_');
        $sql .= " GROUP BY i.id_insumo";
        $sql .= " ORDER BY cantidad_vendida DESC, total_ventas DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCompras(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT c.id_compra,
                       c.numero_factura AS numero_compra,
                       c.fecha_emision AS fecha_compra,
                       c.total_compra,
                       c.estado_factura AS estado,
                       pr.razon_social AS proveedor,
                       COUNT(cd.id_compra) AS items
                FROM compra c
                LEFT JOIN proveedor pr ON c.id_proveedor = pr.id_proveedor
                LEFT JOIN compra_detalle cd ON c.id_compra = cd.id_compra
                WHERE 1=1";

        $sql .= $this->buildDateFilter('c.fecha_emision', $desde, $hasta, $params);
        $sql .= " GROUP BY c.id_compra ORDER BY c.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPagos(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT pp.id_pago,
                       pp.fecha_pago,
                       pp.monto_total AS monto_total,
                       pp.tipo_pago_general,
                       pp.estado,
                       pr.razon_social AS proveedor,
                       c.numero_factura AS numero_compra
                FROM pago_proveedor_cabecera pp
                LEFT JOIN compra c ON pp.id_compra = c.id_compra
                LEFT JOIN proveedor pr ON c.id_proveedor = pr.id_proveedor
                WHERE 1=1";

        $sql .= $this->buildDateFilter('pp.fecha_pago', $desde, $hasta, $params);
        $sql .= " ORDER BY pp.fecha_pago DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCobros(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT pc.id_pago,
                       pc.fecha_pago,
                       pc.monto_total AS monto_total,
                       pc.tipo_pago_general,
                       pc.estado,
                       CONCAT(cl.nombre, ' ', cl.apellido) AS cliente,
                       v.numero_factura
                FROM pago_cliente_cabecera pc
                LEFT JOIN venta v ON pc.id_venta = v.id_venta
                LEFT JOIN cliente cl ON v.id_cliente = cl.id_cliente
                WHERE 1=1";

        $sql .= $this->buildDateFilter('pc.fecha_pago', $desde, $hasta, $params);
        $sql .= " ORDER BY pc.fecha_pago DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDevoluciones(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT v.id_venta,
                   v.numero_factura,
                   v.fecha_emision,
                   v.total_factura,
                   v.estado_factura,
                   c.razon_social AS nombre_cliente,
                   c.ruc_ci AS ruc_ci,
                   v.observaciones AS motivo
            FROM venta v
                JOIN cliente c ON v.id_cliente = c.id_cliente
                WHERE LOWER(v.estado_factura) IN ('anulada', 'devuelta', 'cancelada')";

        $sql .= $this->buildDateFilter('v.fecha_emision', $desde, $hasta, $params);
        $sql .= " ORDER BY v.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPedidos(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT p.id_pedido,
                       COALESCE(c.razon_social, p.nombre_cliente_manual) AS cliente,
                       p.fecha_entrega AS fecha,
                       p.estado AS estado,
                       COALESCE(p.precio_final, 0) AS total,
                       COALESCE((SELECT COUNT(*) FROM pedido_detalle pd WHERE pd.id_pedido = p.id_pedido), 0) AS items
                FROM pedido p
                LEFT JOIN cliente c ON p.id_cliente = c.id_cliente
                WHERE 1=1";

        $sql .= $this->buildDateFilter('p.fecha_creacion', $desde, $hasta, $params);
        $sql .= " ORDER BY p.fecha_creacion DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConsumoInsumos(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT m.id_movimiento,
                       i.id_insumo,
                       i.nombre_insumo AS insumo,
                       m.motivo,
                       m.cantidad,
                       m.tipo_movimiento,
                       m.fecha_movimiento,
                       u.nombre AS usuario
                FROM movimiento_stock_insumo m
                LEFT JOIN insumo i ON m.id_insumo = i.id_insumo
                LEFT JOIN usuario u ON m.creado_por = u.id_usuario
                WHERE 1=1 AND (m.tipo_movimiento = 'Consumo' OR m.tipo_movimiento = 'Produccion')";

        $sql .= $this->buildDateFilter('m.fecha_movimiento', $desde, $hasta, $params);
        $sql .= " ORDER BY m.fecha_movimiento DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProduccion(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT op.id_orden,
                       op.id_pedido,
                       p.id_pedido AS pedido_id,
                       COALESCE(c.razon_social, p.nombre_cliente_manual) AS cliente,
                       op.fecha_inicio,
                       op.fecha_fin,
                       op.estado,
                       p.precio_final AS total_items
                FROM orden_produccion op
                LEFT JOIN pedido p ON op.id_pedido = p.id_pedido
                LEFT JOIN cliente c ON p.id_cliente = c.id_cliente
                WHERE 1=1";

        $sql .= $this->buildDateFilter('op.fecha_inicio', $desde, $hasta, $params);
        $sql .= " ORDER BY op.fecha_inicio DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStock(?string $desde = null, ?string $hasta = null): array
    {
        // Show current stock for insumos and productos
        $params = [];

        $sql = "SELECT 'insumo' AS tipo, i.id_insumo AS id_item, i.nombre_insumo AS nombre, COALESCE(i.stock, 0) AS cantidad, 'Insumo' AS unidad
                FROM insumo i
                UNION ALL
                SELECT 'producto' AS tipo, sp.id_producto AS id_item, p.nombre_producto AS nombre, COALESCE(sp.cantidad, 0) AS cantidad, 'Producto' AS unidad
                FROM stock_producto sp
                LEFT JOIN producto p ON sp.id_producto = p.id_producto
                ORDER BY nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCaja(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $sql = "SELECT id, id_caja, id_usuario, fecha, tipo, monto, observacion, creado_por
                FROM (
                    SELECT id_apertura AS id, id_caja, id_usuario, fecha_apertura AS fecha, 'Apertura' AS tipo, monto_inicial AS monto, observacion, creado_por
                    FROM apertura_caja
                    UNION ALL
                    SELECT id_cierre AS id, id_caja, id_usuario, fecha_cierre AS fecha, 'Cierre' AS tipo, monto_final AS monto, observacion, creado_por
                    FROM cierre_caja
                    UNION ALL
                    SELECT id_arqueo AS id, id_caja, id_usuario, fecha_arqueo AS fecha, 'Arqueo' AS tipo, monto_real AS monto, observacion, creado_por
                    FROM arqueo_caja
                ) q WHERE 1=1";

        $sql .= $this->buildDateFilter('fecha', $desde, $hasta, $params);
        $sql .= " ORDER BY fecha DESC";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMermas(?string $desde = null, ?string $hasta = null): array
    {
        $params = [];

        $filterInsumo = $this->buildDateFilter('m.fecha_movimiento', $desde, $hasta, $params, 'i_');
        $filterProducto = $this->buildDateFilter('m.fecha_movimiento', $desde, $hasta, $params, 'p_');

        $sql = "
            (SELECT m.id_movimiento, 'Insumo' AS tipo, i.nombre_insumo AS articulo,
                    m.motivo, m.cantidad, m.tipo_movimiento, m.fecha_movimiento,
                    u.nombre AS usuario
             FROM movimiento_stock_insumo m
             LEFT JOIN insumo i ON m.id_insumo = i.id_insumo
             LEFT JOIN usuario u ON m.creado_por = u.id_usuario
             WHERE m.tipo_movimiento = 'Ajuste' {$filterInsumo})

            UNION ALL

            (SELECT m.id_movimiento, 'Producto' AS tipo, p.nombre_producto AS articulo,
                    NULL AS motivo, m.cantidad, m.tipo_movimiento, m.fecha_movimiento,
                    u.nombre AS usuario
             FROM movimiento_stock_producto m
             LEFT JOIN producto p ON m.id_producto = p.id_producto
             LEFT JOIN usuario u ON m.creado_por = u.id_usuario
             WHERE m.tipo_movimiento = 'Ajuste' {$filterProducto})

            ORDER BY fecha_movimiento DESC
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindDateFilters($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getUsuarios(): array
    {
        $sql = "SELECT u.id_usuario, u.ci_usuario AS usuario, u.nombre, u.apellido,
                       u.email, u.estado, r.nombre_rol AS rol
                FROM usuario u
                LEFT JOIN rol r ON u.id_rol = r.id_rol
                ORDER BY u.id_usuario DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoles(): array
    {
        $sql = "SELECT id_rol, nombre_rol, descripcion, estado
                FROM rol
                ORDER BY id_rol DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClientes(): array
    {
        $sql = "SELECT id_cliente, nombre, apellido, ci_ruc, email,
                       telefono, celular, tipo_cliente, estado
                FROM cliente
                ORDER BY id_cliente DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getArticulos(): array
    {
        $sql = "SELECT id_producto, nombre_producto, tipo_producto,
                       descripcion, costo_produccion, precio_base, estado
                FROM producto
                ORDER BY id_producto DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategorias(): array
    {
        $sql = "SELECT id_categoria, nombre_categoria, descripcion, estado
                FROM categoria
                ORDER BY id_categoria DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMarcas(): array
    {
        try {
            $sql = "SELECT id_marca, nombre_marca, descripcion, estado
                    FROM marca
                    ORDER BY id_marca DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getProveedores(): array
    {
        $sql = "SELECT id_proveedor,
                       razon_social AS nombre_proveedor,
                       '' AS representante,
                       correo AS email,
                       telefono,
                       celular,
                       direccion AS ciudad,
                       estado
                FROM proveedor
                ORDER BY id_proveedor DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
