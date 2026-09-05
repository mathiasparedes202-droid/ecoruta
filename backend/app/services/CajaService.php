<?php

namespace App\Services;

use App\Repositories\CajaRepository;

class CajaService
{
    private CajaRepository $cajaRepo;

    public function __construct()
    {
        $this->cajaRepo = new CajaRepository();
    }

    public function obtenerEstadoCaja(): array
    {
        $idCaja = $this->cajaRepo->getCajaId();
        if ($idCaja === null) {
            return [
                'abierta' => false,
                'caja' => null,
                'ultima_apertura' => null,
                'ultimo_cierre' => null,
            ];
        }

        $ultimaApertura = $this->cajaRepo->getLastApertura($idCaja);
        $ultimoCierre = $this->cajaRepo->getLastCierre($idCaja);

        $abierta = false;
        if ($ultimaApertura && (!$ultimoCierre || strtotime($ultimaApertura['fecha_creacion']) > strtotime($ultimoCierre['fecha_creacion']))) {
            $abierta = true;
        }

        return [
            'abierta' => $abierta,
            'caja' => $this->cajaRepo->getCajaInfo($idCaja),
            'ultima_apertura' => $ultimaApertura,
            'ultimo_cierre' => $ultimoCierre,
        ];
    }

    public function abrirCaja(int $usuarioId, float $montoInicial = 0, ?string $observacion = null): int
    {
        return $this->cajaRepo->abrirCaja($usuarioId, $montoInicial, $observacion);
    }

    public function cerrarCaja(int $usuarioId, float $montoFinal, ?string $observacion = null): int
    {
        $idCaja = $this->cajaRepo->getCajaId();
        if ($idCaja === null) {
            throw new \Exception('No hay una caja configurada para cerrar');
        }

        $ultimaApertura = $this->cajaRepo->getLastApertura($idCaja);
        $ultimoCierre = $this->cajaRepo->getLastCierre($idCaja);

        if (!$ultimaApertura || ($ultimoCierre && strtotime($ultimoCierre['fecha_creacion']) >= strtotime($ultimaApertura['fecha_creacion']))) {
            throw new \Exception('No hay caja abierta para cerrar');
        }

        return $this->cajaRepo->cerrarCaja($usuarioId, $montoFinal, $observacion);
    }

    public function obtenerMovimientosCaja(): array
    {
        $idCaja = $this->cajaRepo->getCajaId();
        if ($idCaja === null) {
            return [];
        }

        return $this->cajaRepo->getMovimientos($idCaja);
    }
}
