<?php

declare(strict_types=1);

function database(): PDO
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
    $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'ecoruta_db';
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
    $password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $ssl = filter_var($_ENV['DB_SSL'] ?? getenv('DB_SSL') ?? false, FILTER_VALIDATE_BOOLEAN);
    $sslCa = $_ENV['DB_SSL_CA'] ?? getenv('DB_SSL_CA') ?: null;

    if (!$sslCa && $ssl) {
        if (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
            $sslCa = '/etc/ssl/certs/ca-certificates.crt';
        } elseif (file_exists('C:\\xampp\\apache\\bin\\curl-ca-bundle.crt')) {
            $sslCa = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
        }
    }

    if ($sslCa && file_exists($sslCa)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    } elseif ($ssl) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $connection = new PDO($dsn, $user, $password, $options);

    return $connection;
}
