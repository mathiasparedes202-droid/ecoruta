<?php

namespace Config;

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../'); 
$dotenv->load();

/**
 * App
 *
 * Clase helper para acceder a variables de configuración y entorno
 */
class App
{
    /**
     * Retorna el valor de una variable de entorno
     *
     * @param string $key Nombre de la variable
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    public static function env(string $key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Retorna el nombre de la aplicación
     *
     * @return string
     */
    public static function name(): string
    {
        return self::env('APP_NAME', 'MiApp');
    }

    /**
     * Retorna la URL base de la aplicación
     *
     * @return string
     */
    public static function url(): string
    {
        return self::env('APP_URL', 'https://ecoruta.page.gd');
    }

    /**
     * Retorna el entorno actual de la aplicación
     *
     * @return string
     */
    public static function environment(): string
    {
        return self::env('APP_ENV', 'production');
    }

    /**
     * Indica si el entorno es de desarrollo
     *
     * @return bool
     */
    public static function isDev(): bool
    {
        return self::environment() === 'development';
    }

    public static function jwtSecret(): string
    {
        return self::env('JWT_SECRET', 'default_secret');
    }

    public static function jwtExpiration(): int
    {
        return (int) self::env('JWT_EXPIRE', 3600);
    }
}