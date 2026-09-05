<?php

namespace Core;

class Request
{
    private $method;
    private $uri;
    private $queryParams;
    private $body;
    private $headers;
    private $user = null;

    /**
     * Inicializa los datos principales de la petición HTTP.
     * Obtiene método, URI, parámetros de consulta, headers y cuerpo.
     *
     * @return void
     */
    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        $scriptBase = str_replace('\\', '/', $scriptName);

        if ($scriptBase !== '' && $uri === $scriptBase) {
            $uri = '/';
        } elseif ($scriptBase !== '' && str_starts_with($uri, $scriptBase . '/')) {
            $uri = substr($uri, strlen($scriptBase));
        } elseif ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }

        if ($uri === '') {
            $uri = '/';
        }

        $this->uri = $uri;
        $this->queryParams = $_GET;
        $this->headers = $this->getHeaders();
        $this->body = $this->parseBody();
    }

    /**
     * Obtiene el método HTTP de la petición.
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Obtiene la URI de la petición sin parámetros.
     *
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Obtiene todos los parámetros de la query.
     *
     * @return array
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * Obtiene un parámetro específico de la query.
     *
     * @param string $key Nombre del parámetro.
     * @param mixed $default Valor por defecto si no existe.
     * @return mixed
     */
    public function getQueryParam($key, $default = null)
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Obtiene todos los datos del cuerpo de la petición.
     *
     * @return array
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Obtiene un campo específico del cuerpo de la petición.
     *
     * @param string $key Nombre del campo.
     * @param mixed $default Valor por defecto si no existe.
     * @return mixed
     */
    public function getBodyParam($key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Obtiene todos los headers de la petición.
     * Incluye fallback para servidores sin soporte de getallheaders.
     *
     * @return array
     */
    public function getHeaders()
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
        } else {
            // Construye los headers manualmente desde $_SERVER.
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $key = str_replace('_', '-', substr($name, 5));
                    $headers[strtolower($key)] = $value;
                }
            }

            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $headers['authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
            }
        }

        return $headers;
    }

    /**
     * Obtiene un header específico.
     *
     * @param string $key Nombre del header.
     * @param mixed $default Valor por defecto si no existe.
     * @return mixed
     */
    public function getHeader($key, $default = null)
    {
        $lookup = strtolower($key);
        return $this->headers[$lookup] ?? $default;
    }

    /**
     * Determina si la petición contiene JSON.
     *
     * @return bool
     */
    public function isJson()
    {
        $contentType = $this->getHeader('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    /**
     * Parsea el cuerpo de la petición.
     * Soporta JSON y form-data.
     *
     * @return array
     */
    private function parseBody()
    {
        if ($this->isJson()) {
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);

            return is_array($data) ? $data : [];
        }

        return $_POST ?? [];
    }

    /**
     * Obtiene el token Bearer del header Authorization.
     *
     * @return string|null
     */
    public function getBearerToken()
    {
        $authHeader = $this->getHeader('Authorization');

        if (!$authHeader) {
            return null;
        }

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Asigna el usuario autenticado a la request.
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Devuelve el usuario autenticado de la request.
     */
    public function getUser()
    {
        return $this->user;
    }
}
