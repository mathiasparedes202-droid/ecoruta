<?php

namespace App\Models;

class PagoProveedorDetalle
{
    public ?int $id_detalle = null;
    public int $id_pago_proveedor;
    public string $metodo_pago; // Efectivo, Transferencia, Cheque
    public ?string $numero_comprobante = null; // Número de cheque o referencia
    public ?string $banco = null;
    public ?string $fecha_vencimiento = null; // Para cheques
    public string $estado_pago = 'Pendiente'; // Pendiente, Confirmado, Rechazado
    public ?string $observaciones = null;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_detalle = $data['id_detalle'] ?? null;
            $this->id_pago_proveedor = $data['id_pago_proveedor'] ?? 0;
            $this->metodo_pago = $data['metodo_pago'] ?? 'Efectivo';
            $this->numero_comprobante = $data['numero_comprobante'] ?? null;
            $this->banco = $data['banco'] ?? null;
            $this->fecha_vencimiento = $data['fecha_vencimiento'] ?? null;
            $this->estado_pago = $data['estado_pago'] ?? 'Pendiente';
            $this->observaciones = $data['observaciones'] ?? null;
        }
    }
}
