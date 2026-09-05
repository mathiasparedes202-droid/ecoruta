<?php

namespace Core;

class Response
{
    private $statusCode = 200;
    private $headers = [];

    // Establece el código de estado HTTP.
    public function setStatusCode(int $code)
    {
        $this->statusCode = $code;
    }

    // Obtiene el código de estado actual.
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    // Añade un header a la respuesta
    // string $key - nombre del header
    // string $value - valor del header
    public function addHeader(string $key, string $value)
    {
        $this->headers[$key] = $value;
    }

    // Envía los headers almacenados
    private function sendHeaders()
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
    }

    /**
     * Envía una respuesta JSON al cliente.
     *
     * @param array $data Datos a enviar.
     * @param ?int $statusCode Código HTTP opcional.
     * @return void
     */
    public function json(array $data, ?int $statusCode = null)
    {
        if ($statusCode !== null) {
            $this->setStatusCode($statusCode);
        }

        $this->addHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->sendHeaders();

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Envía un mensaje de error estándar
     *
     * @param string $message - Mensaje de error
     * @param int $statusCode - Código HTTP
     * @param mixed $debug - Información de depuración (opcional)
     * @return void
     */
    public function error(string $message, int $statusCode = 500, $debug = null)
    {
        $this->setStatusCode($statusCode);

        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if ($debug !== null) {
            $response['debug'] = $debug;
        }

        $this->json($response, $statusCode);
    }

    /**
     * Envía un mensaje de éxito estándar
     *
     * @param string $message - Mensaje de éxito
     * @param mixed $data - Datos opcionales a enviar
     * @param int $statusCode - Código HTTP
     * @return void
     */
    public function success(string $message, $data = null, int $statusCode = 200)
    {
        $this->setStatusCode($statusCode);

        $response = [
            'status' => 'success',
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->json($response, $statusCode);
    }

    /**
     * Redirección a otra URL
     *
     * @param string $url - URL de destino
     * @param int $statusCode - Código HTTP de redirección
     * @return void
     */
    public function redirect(string $url, int $statusCode = 302)
    {
        $this->setStatusCode($statusCode);
        header("Location: $url");
        exit;
    }
}