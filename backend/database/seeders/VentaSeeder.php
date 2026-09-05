<?php

use Config\Database;

class VentaSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $ventas = [
            [
                'id_cliente' => 1,
                'numero_factura' => '2024-V-0001',
                'timbrado' => 'T-0001',
                'fecha_emision' => '2024-03-05',
                'estado_factura' => 'Vigente',
                'tipo_comprobante' => 'Factura',
                'numero_comprobante' => 'F-0001',
                'delivery_option' => 'Retiro',
                'direccion_entrega' => null,
                'total_factura' => 240000.00,
                'iva_10' => 24000.00,
                'subtotal_iva_10' => 216000.00,
                'total_iva' => 24000.00,
                'liquidacion_iva_10' => 7200.00,
                'total_liquidacion' => 7200.00,
                'condicion_venta' => 'Contado',
                'plazo' => null,
                'creado_por' => 1,
                'detalles' => [
                    ['id_producto' => 1, 'cantidad' => 1, 'precio_unitario' => 240000.00, 'iva_tipo' => 10],
                ],
            ],
            [
                'id_cliente' => 2,
                'numero_factura' => '2024-V-0002',
                'timbrado' => 'T-0001',
                'fecha_emision' => '2024-03-07',
                'estado_factura' => 'Vigente',
                'tipo_comprobante' => 'Factura',
                'numero_comprobante' => 'F-0002',
                'delivery_option' => 'Delivery',
                'direccion_entrega' => 'Av. España 847, Asunción',
                'total_factura' => 700000.00,
                'iva_10' => 70000.00,
                'subtotal_iva_10' => 630000.00,
                'total_iva' => 70000.00,
                'liquidacion_iva_10' => 21000.00,
                'total_liquidacion' => 21000.00,
                'condicion_venta' => 'Crédito',
                'plazo' => 15,
                'creado_por' => 1,
                'detalles' => [
                    ['id_producto' => 2, 'cantidad' => 1, 'precio_unitario' => 350000.00, 'iva_tipo' => 10],
                    ['id_producto' => 3, 'cantidad' => 1, 'precio_unitario' => 150000.00, 'iva_tipo' => 10],
                ],
            ],
        ];

        $ventaQuery = "INSERT INTO venta (
            id_cliente, numero_factura, timbrado, fecha_emision, estado_factura, tipo_comprobante, numero_comprobante, delivery_option, direccion_entrega, total_factura, iva_5, iva_10, subtotal_iva_exenta, subtotal_iva_5, subtotal_iva_10, total_iva, liquidacion_iva_5, liquidacion_iva_10, total_liquidacion, condicion_venta, plazo, creado_por
        ) VALUES (
            :id_cliente, :numero_factura, :timbrado, :fecha_emision, :estado_factura, :tipo_comprobante, :numero_comprobante, :delivery_option, :direccion_entrega, :total_factura, :iva_5, :iva_10, :subtotal_iva_exenta, :subtotal_iva_5, :subtotal_iva_10, :total_iva, :liquidacion_iva_5, :liquidacion_iva_10, :total_liquidacion, :condicion_venta, :plazo, :creado_por
        )";

        $detalleQuery = "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, iva_tipo, subtotal, creado_por) VALUES (:id_venta, :id_producto, :cantidad, :precio_unitario, :iva_tipo, :subtotal, :creado_por)";

        $stmt = $db->prepare($ventaQuery);
        $detStmt = $db->prepare($detalleQuery);

        foreach ($ventas as $v) {
            $detalles = $v['detalles'];
            unset($v['detalles']);

            // rellenar campos vacíos
            $defaults = [
                'iva_5' => 0.00,
                'subtotal_iva_exenta' => 0.00,
                'subtotal_iva_5' => 0.00,
            ];
            $data = array_merge($defaults, $v);

            try {
                $stmt->execute($data);
                $ventaId = $db->lastInsertId();

                foreach ($detalles as $d) {
                    $subtotal = $d['cantidad'] * $d['precio_unitario'];
                    $detStmt->execute(array_merge($d, [
                        'id_venta' => $ventaId,
                        'subtotal' => $subtotal,
                        'creado_por' => $data['creado_por']
                    ]));
                }
            } catch (Exception $e) {
                // ignorar duplicados
            }
        }

        echo "Ventas sembradas.\n";
    }
}
