<?php

namespace App\Models;

class CompraHistorial
{
    public ?int $id_historial = null;
    public int $id_compra;
    public ?int $usuario_id = null;
    public string $campo_modificado; // Campo que fue modificado
    public ?string $valor_anterior = null;
    public ?string $valor_nuevo = null;
    public ?string $motivo = null;
    public ?bool $crear_nota_credito = null;
    public ?string $fecha_cambio = null;
    public ?string $nombre_usuario = null; // Para mostrar quien hizo el cambio

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id_historial = $data['id_historial'] ?? null;
            $this->id_compra = $data['id_compra'] ?? 0;
            $this->usuario_id = $data['usuario_id'] ?? null;
            $this->campo_modificado = $data['campo_modificado'] ?? '';
            $this->valor_anterior = $data['valor_anterior'] ?? null;
            $this->valor_nuevo = $data['valor_nuevo'] ?? null;
            $this->motivo = $data['motivo'] ?? null;
            $this->crear_nota_credito = $data['crear_nota_credito'] ?? null;
            $this->fecha_cambio = $data['fecha_cambio'] ?? null;
            $this->nombre_usuario = $data['nombre_usuario'] ?? null;
        }
    }
}
