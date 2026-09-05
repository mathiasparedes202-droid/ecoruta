<?php

namespace App\Services;

use App\Repositories\ReporteRepository;

class ReporteService
{
    private ReporteRepository $reporteRepo;

    public function __construct()
    {
        $this->reporteRepo = new ReporteRepository();
    }

    public function obtenerReporte(string $section, ?string $desde = null, ?string $hasta = null): array
    {
        switch ($section) {
            case 'ventas':
            case 'ventas_generales':
                return $this->reporteRepo->getVentas($desde, $hasta);
            case 'ventas_por_cliente':
                return $this->reporteRepo->getVentasPorCliente($desde, $hasta);
            case 'ventas_por_articulo':
                return $this->reporteRepo->getVentasPorArticulo($desde, $hasta);
            case 'ventas_detallado':
                return $this->reporteRepo->getVentasDetalle($desde, $hasta);
            case 'ventas_por_condicion':
                return $this->reporteRepo->getVentasPorCondicion($desde, $hasta);
            case 'ventas_credito_pendiente':
                return $this->reporteRepo->getVentasCreditoPendiente($desde, $hasta);
            case 'ventas_credito_saldo_cero':
                return $this->reporteRepo->getVentasCreditoSaldoCero($desde, $hasta);
            case 'top-productos':
                return $this->reporteRepo->getTopProductos($desde, $hasta);
            case 'compras':
                return $this->reporteRepo->getCompras($desde, $hasta);
            case 'pedidos':
                return $this->reporteRepo->getPedidos($desde, $hasta);
            case 'consumo-insumos':
            case 'consumo_insumos':
                return $this->reporteRepo->getConsumoInsumos($desde, $hasta);
            case 'produccion':
                return $this->reporteRepo->getProduccion($desde, $hasta);
            case 'stock':
                return $this->reporteRepo->getStock($desde, $hasta);
            case 'caja':
                return $this->reporteRepo->getCaja($desde, $hasta);
            case 'pagos':
                return $this->reporteRepo->getPagos($desde, $hasta);
            case 'cobros':
                return $this->reporteRepo->getCobros($desde, $hasta);
            case 'devoluciones':
                return $this->reporteRepo->getDevoluciones($desde, $hasta);
            case 'mermas':
                return $this->reporteRepo->getMermas($desde, $hasta);
            case 'usuarios':
                return $this->reporteRepo->getUsuarios();
            case 'roles':
                return $this->reporteRepo->getRoles();
            case 'clientes':
                return $this->reporteRepo->getClientes();
            case 'articulos':
                return $this->reporteRepo->getArticulos();
            case 'categorias':
                return $this->reporteRepo->getCategorias();
            case 'marcas':
                return $this->reporteRepo->getMarcas();
            case 'proveedores':
                return $this->reporteRepo->getProveedores();
            default:
                throw new \InvalidArgumentException('Sección de reporte inválida');
        }
    }
}
