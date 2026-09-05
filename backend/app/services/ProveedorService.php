<?php

namespace App\Services;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use App\Repositories\CompraRepository;

class ProveedorService
{
    private ProveedorRepository $proveedorRepo;
    private CompraRepository $compraRepo;

    public function __construct()
    {
        $this->proveedorRepo = new ProveedorRepository();
        $this->compraRepo = new CompraRepository();
    }

    public function listarProveedores(): array
    {
        return $this->proveedorRepo->all();
    }

    public function crearProveedor(array $data): Proveedor
    {
        if (empty($data['ruc']) || empty($data['razon_social'])) {
            throw new \Exception('RUC/CI y razón social son obligatorios');
        }

        $proveedor = new Proveedor([
            'ruc' => $data['ruc'],
            'razon_social' => $data['razon_social'],
            'direccion' => $data['direccion'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'celular' => $data['celular'] ?? null,
            'correo' => $data['correo'] ?? null,
            'estado' => $data['estado'] ?? 1,
            'creado_por' => $data['creado_por'] ?? null,
        ]);

        $id = $this->proveedorRepo->create($proveedor);
        $proveedor->id_proveedor = $id;

        return $this->proveedorRepo->findById($id);
    }

    public function obtenerProveedor(int $id): ?Proveedor
    {
        return $this->proveedorRepo->findById($id);
    }

    public function actualizarProveedor(int $id, array $data): Proveedor
    {
        $proveedor = $this->proveedorRepo->findById($id);
        if (!$proveedor) {
            throw new \Exception('Proveedor no encontrado');
        }

        $proveedor->ruc = $data['ruc'] ?? $proveedor->ruc;
        $proveedor->razon_social = $data['razon_social'] ?? $proveedor->razon_social;
        $proveedor->direccion = $data['direccion'] ?? $proveedor->direccion;
        $proveedor->telefono = $data['telefono'] ?? $proveedor->telefono;
        $proveedor->celular = $data['celular'] ?? $proveedor->celular;
        $proveedor->correo = $data['correo'] ?? $proveedor->correo;
        $proveedor->estado = $data['estado'] ?? $proveedor->estado;
        $proveedor->creado_por = $data['creado_por'] ?? $proveedor->creado_por;

        $this->proveedorRepo->update($proveedor);

        return $this->proveedorRepo->findById($id);
    }

    public function eliminarProveedor(int $id): bool
    {
        $compras = $this->compraRepo->findByProveedor($id);
        if (count($compras) > 0) {
            throw new \Exception('No se puede eliminar el proveedor porque tiene compras asociadas');
        }

        return $this->proveedorRepo->delete($id);
    }
}
