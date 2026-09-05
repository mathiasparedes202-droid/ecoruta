<?php

namespace App\Repositories;

use Config\Database;
use PDO;

class CajaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function getCajaId(): ?int
    {
        $stmt = $this->db->query("SELECT id_caja FROM caja ORDER BY id_caja ASC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);
        return $caja ? (int)$caja['id_caja'] : null;
    }

    public function ensureCajaExists(int $usuarioId = null): int
    {
        $idCaja = $this->getCajaId();
        if ($idCaja !== null) {
            return $idCaja;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO caja (nombre, tipo, id_usuario, estado, saldo_actual) VALUES (:nombre, :tipo, :id_usuario, 1, 0)"
        );
        $stmt->execute([
            'nombre' => 'Caja Principal',
            'tipo' => 'Principal',
            'id_usuario' => $usuarioId,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getLastApertura(int $idCaja): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM apertura_caja WHERE id_caja = :id_caja ORDER BY fecha_creacion DESC LIMIT 1");
        $stmt->bindParam(':id_caja', $idCaja, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getLastCierre(int $idCaja): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM cierre_caja WHERE id_caja = :id_caja ORDER BY fecha_creacion DESC LIMIT 1");
        $stmt->bindParam(':id_caja', $idCaja, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getCajaInfo(int $idCaja): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM caja WHERE id_caja = :id_caja LIMIT 1");
        $stmt->bindParam(':id_caja', $idCaja, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public function abrirCaja(int $idUsuario, float $montoInicial, ?string $observacion = null): int
    {
        $idCaja = $this->ensureCajaExists($idUsuario);

        $stmt = $this->db->prepare(
            "INSERT INTO apertura_caja (id_caja, id_usuario, fecha_apertura, monto_inicial, observacion, creado_por) VALUES (:id_caja, :id_usuario, NOW(), :monto_inicial, :observacion, :creado_por)"
        );
        $stmt->execute([
            'id_caja' => $idCaja,
            'id_usuario' => $idUsuario,
            'monto_inicial' => $montoInicial,
            'observacion' => $observacion,
            'creado_por' => $idUsuario,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function cerrarCaja(int $idUsuario, float $montoFinal, ?string $observacion = null): int
    {
        $idCaja = $this->getCajaId();
        if ($idCaja === null) {
            throw new \Exception('No existe una caja configurada');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO cierre_caja (id_caja, id_usuario, fecha_cierre, monto_final, observacion, creado_por) VALUES (:id_caja, :id_usuario, NOW(), :monto_final, :observacion, :creado_por)"
        );
        $stmt->execute([
            'id_caja' => $idCaja,
            'id_usuario' => $idUsuario,
            'monto_final' => $montoFinal,
            'observacion' => $observacion,
            'creado_por' => $idUsuario,
        ]);

        $update = $this->db->prepare("UPDATE caja SET saldo_actual = :saldo_actual WHERE id_caja = :id_caja");
        $update->execute(['saldo_actual' => $montoFinal, 'id_caja' => $idCaja]);

        return (int)$this->db->lastInsertId();
    }

    public function getMovimientos(int $idCaja): array
    {
        $query = "SELECT id_apertura AS id, id_caja, id_usuario, fecha_apertura AS fecha, 'Apertura' AS tipo, monto_inicial AS monto, observacion, creado_por
                  FROM apertura_caja
                  WHERE id_caja = :id_caja_apertura
                  UNION ALL
                  SELECT id_cierre AS id, id_caja, id_usuario, fecha_cierre AS fecha, 'Cierre' AS tipo, monto_final AS monto, observacion, creado_por
                  FROM cierre_caja
                  WHERE id_caja = :id_caja_cierre
                  UNION ALL
                  SELECT id_arqueo AS id, id_caja, id_usuario, fecha_arqueo AS fecha, 'Arqueo' AS tipo, monto_real AS monto, observacion, creado_por
                  FROM arqueo_caja
                  WHERE id_caja = :id_caja_arqueo
                  ORDER BY fecha DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id_caja_apertura', $idCaja, PDO::PARAM_INT);
        $stmt->bindParam(':id_caja_cierre', $idCaja, PDO::PARAM_INT);
        $stmt->bindParam(':id_caja_arqueo', $idCaja, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
