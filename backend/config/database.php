<?php

namespace Config;

use PDO;
use PDOException;

/**
 * Database
 *
 * Clase que maneja la conexión a la base de datos MySQL usando PDO.
 * Lee las configuraciones desde variables de entorno
 */
class Database
{
    /**
     * @var PDO|null Instancia de PDO
     */
    private ?PDO $connection = null;

    /**
     * Conecta a la base de datos
     *
     * @return PDO
     */
    public function connect(): PDO
    {
        // Si ya hay conexión, la retornamos
        if ($this->connection) {
            return $this->connection;
        }

        // Leer variables de entorno
        $host = App::env('DB_HOST', '127.0.0.1');
        $db   = App::env('DB_NAME', 'floracia_db');
        $user = App::env('DB_USER', 'root');
        $pass = App::env('DB_PASS', '');
        $charset = 'utf8mb4';

        // DSN de PDO
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        // Opciones recomendadas de PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Excepciones en errores
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch como array asociativo
            PDO::ATTR_PERSISTENT         => true,                  // Conexión persistente
            PDO::ATTR_EMULATE_PREPARES   => false                  // Preparados reales
        ];

        try {
            // Crear conexión PDO
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Manejo de error
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }

        return $this->connection;
    }
}