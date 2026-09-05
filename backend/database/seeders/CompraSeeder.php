<?php

use Config\Database;

class CompraSeeder
{
    public function run()
    {
        $db = (new Database())->connect();

        $compras = [
            [
                'id_proveedor' => 1,
                'numero_factura' => '001-001-0000001',
                'timbrado' => '12345678',
                'fecha_emision' => '2024-01-01',
                'estado_factura' => 'Vigente',
                'tipo_comprobante' => 'Factura',
                'condicion_compra' => 'Contado',
                'plazo' => null,
                'observaciones' => 'Compra inicial de rosas nacionales',
                    'total_compra' => 1450000.00,
                'iva_5' => 0.00,
                'iva_10' => 125000.00,
                'subtotal_iva_exenta' => 0.00,
                'subtotal_iva_5' => 0.00,
                'subtotal_iva_10' => 1125000.00,
                'total_iva' => 125000.00,
                'liquidacion_iva_5' => 0.00,
                'liquidacion_iva_10' => 37500.00,
                'total_liquidacion' => 37500.00,
                'creado_por' => 1,
                'detalles' => [
                    ['id_insumo' => 1, 'cantidad' => 10, 'precio_unitario' => 125000.00, 'iva_tipo' => 10],
                ],
            ],
            [
                'id_proveedor' => 2,
                'numero_factura' => '001-002-0000002',
                'timbrado' => '87654321',
                'fecha_emision' => '2024-01-15',
                'estado_factura' => 'Vigente',
                'tipo_comprobante' => 'Factura',
                'condicion_compra' => 'Crédito',
                'plazo' => 30,
                'observaciones' => 'Compra de tulipanes y accesorios',
                'total_compra' => 980000.00,
                    'total_compra' => 1100000.00,
                'iva_5' => 0.00,
                'iva_10' => 98000.00,
                'subtotal_iva_exenta' => 0.00,
                'subtotal_iva_5' => 0.00,
                'subtotal_iva_10' => 882000.00,
                'total_iva' => 98000.00,
                'liquidacion_iva_5' => 0.00,
                'liquidacion_iva_10' => 29400.00,
                'total_liquidacion' => 29400.00,
                'creado_por' => 1,
                'detalles' => [
                    ['id_insumo' => 2, 'cantidad' => 15, 'precio_unitario' => 65000.00, 'iva_tipo' => 10],
                ],
            ],
            [
                'id_proveedor' => 3,
                'numero_factura' => '001-003-0000003',
                'timbrado' => '11223344',
                'fecha_emision' => '2024-02-01',
                'estado_factura' => 'Vigente',
                'tipo_comprobante' => 'Factura',
                'condicion_compra' => 'Contado',
                'plazo' => null,
                'observaciones' => 'Compra de cintas decorativas y papel kraft',
                    'total_compra' => 1600000.00,
                'iva_5' => 0.00,
                'iva_10' => 140000.00,
                'subtotal_iva_exenta' => 0.00,
                'subtotal_iva_5' => 0.00,
                'subtotal_iva_10' => 1260000.00,
                'total_iva' => 140000.00,
                'liquidacion_iva_5' => 0.00,
                'liquidacion_iva_10' => 42000.00,
                'total_liquidacion' => 42000.00,
                'creado_por' => 1,
                'detalles' => [
                    ['id_insumo' => 3, 'cantidad' => 20, 'precio_unitario' => 35000.00, 'iva_tipo' => 10],
                    ['id_insumo' => 5, 'cantidad' => 10, 'precio_unitario' => 42000.00, 'iva_tipo' => 10],
                ],
            ],
            [
                'id_proveedor' => 4,
                'numero_factura' => '001-004-0000004',
                'timbrado' => '55667788',
                'fecha_emision' => '2024-02-15',
                'estado_factura' => 'Vigente',
                'tipo_comprobante' => 'Factura',
                'condicion_compra' => 'Contado',
                'plazo' => null,
                'observaciones' => 'Compra de lirios blancos para eventos',
                    'total_compra' => 950000.00,
                'iva_5' => 0.00,
                'iva_10' => 86000.00,
                'subtotal_iva_exenta' => 0.00,
                'subtotal_iva_5' => 0.00,
                'subtotal_iva_10' => 774000.00,
                'total_iva' => 86000.00,
                'liquidacion_iva_5' => 0.00,
                'liquidacion_iva_10' => 25800.00,
                'total_liquidacion' => 25800.00,
                'creado_por' => 1,
                'detalles' => [
                    ['id_insumo' => 4, 'cantidad' => 6, 'precio_unitario' => 143000.00, 'iva_tipo' => 10],
                ],
            ],
            [
                'id_proveedor' => 5,
                'numero_factura' => '001-005-0000005',
                'timbrado' => '99887766',
                'fecha_emision' => '2024-03-01',
                'estado_factura' => 'Vigente',
                'tipo_comprobante' => 'Factura',
                'condicion_compra' => 'Contado',
                'plazo' => null,
                'observaciones' => 'Compra de insumos para ramos especiales',
                    'total_compra' => 800000.00,
                'iva_5' => 0.00,
                'iva_10' => 72000.00,
                'subtotal_iva_exenta' => 0.00,
                'subtotal_iva_5' => 0.00,
                'subtotal_iva_10' => 648000.00,
                'total_iva' => 72000.00,
                'liquidacion_iva_5' => 0.00,
                'liquidacion_iva_10' => 21600.00,
                'total_liquidacion' => 21600.00,
                'creado_por' => 1,
                'detalles' => [
                    ['id_insumo' => 5, 'cantidad' => 8, 'precio_unitario' => 90000.00, 'iva_tipo' => 10],
                ],
            ],
        ];

        $query = "INSERT INTO compra (
                    id_proveedor,
                    numero_factura,
                    timbrado,
                    fecha_emision,
                    estado_factura,
                    tipo_comprobante,
                    condicion_compra,
                    plazo,
                    observaciones,
                    total_compra,
                    iva_5,
                    iva_10,
                    subtotal_iva_exenta,
                    subtotal_iva_5,
                    subtotal_iva_10,
                    total_iva,
                    liquidacion_iva_5,
                    liquidacion_iva_10,
                    total_liquidacion,
                    creado_por
                  ) VALUES (
                    :id_proveedor,
                    :numero_factura,
                    :timbrado,
                    :fecha_emision,
                    :estado_factura,
                    :tipo_comprobante,
                    :condicion_compra,
                    :plazo,
                    :observaciones,
                    :total_compra,
                    :iva_5,
                    :iva_10,
                    :subtotal_iva_exenta,
                    :subtotal_iva_5,
                    :subtotal_iva_10,
                    :total_iva,
                    :liquidacion_iva_5,
                    :liquidacion_iva_10,
                    :total_liquidacion,
                    :creado_por
                  )";

        $detailQuery = "INSERT INTO compra_detalle (
                            id_compra,
                            id_insumo,
                            cantidad,
                            precio_unitario,
                            iva_tipo,
                            subtotal
                          ) VALUES (
                            :id_compra,
                            :id_insumo,
                            :cantidad,
                            :precio_unitario,
                            :iva_tipo,
                            :subtotal
                          )";

        $stmt = $db->prepare($query);
        $detailStmt = $db->prepare($detailQuery);

        foreach ($compras as $compraData) {
            $detalles = $compraData['detalles'];
            unset($compraData['detalles']);

            try {
                $stmt->execute($compraData);
                $compraId = $db->lastInsertId();

                foreach ($detalles as $detail) {
                    $subtotal = $detail['cantidad'] * $detail['precio_unitario'];
                    $detailStmt->execute(array_merge($detail, [
                        'id_compra' => $compraId,
                        'subtotal' => $subtotal
                    ]));
                }
            } catch (Exception $e) {
                // Si ya existe, ignorar
            }
        }

        echo "Compras de ejemplo sembradas.\n";
    }
}
