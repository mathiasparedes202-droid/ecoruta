<?php

namespace Core;

class Controller
{
    protected $request;
    protected $response;

    /**
     * Constructor del controlador
     *
     * @param Request $request - Instancia de Request
     * @param Response $response - Instancia de Response
     */
    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Responder con éxito estándar
     *
     * @param string $message - Mensaje de éxito
     * @param mixed $data - Datos opcionales a incluir
     * @param int $statusCode - Código HTTP (por defecto 200)
     * @return void
     */
    protected function respondSuccess(string $message, $data = null, int $statusCode = 200)
    {
        $this->response->success($message, $data, $statusCode);
    }

    /**
     * Responder con error estándar
     *
     * @param string $message - Mensaje de error
     * @param int $statusCode - Código HTTP (por defecto 500)
     * @param mixed $debug - Información de depuración opcional
     * @return void
     */
    protected function respondError(string $message, int $statusCode = 500, $debug = null)
    {
        $this->response->error($message, $statusCode, $debug);
    }

    /**
     * Ejecuta un middleware interno del controlador
     *
     * @param string $middlewareClass - Nombre de la clase del middleware
     * @return void
     */
    protected function runMiddleware(string $middlewareClass)
    {
        if (!class_exists($middlewareClass)) {
            $this->respondError("Middleware $middlewareClass no encontrado", 500);
        }

        $middleware = new $middlewareClass();
        $middleware->handle($this->request, $this->response);
    }

    /**
     * Obtiene un parámetro del body
     *
     * @param string $key - Nombre del campo
     * @param mixed $default - Valor por defecto
     * @return mixed
     */
    protected function bodyParam(string $key, $default = null)
    {
        return $this->request->getBodyParam($key, $default);
    }

    /**
     * Obtiene un parámetro de query
     *
     * @param string $key - Nombre del parámetro
     * @param mixed $default - Valor por defecto
     * @return mixed
     */
    protected function queryParam(string $key, $default = null)
    {
        return $this->request->getQueryParam($key, $default);
    }

    /**
     * Obtiene un header específico
     *
     * @param string $key - Nombre del header
     * @param mixed $default - Valor por defecto
     * @return mixed
     */
    protected function header(string $key, $default = null)
    {
        return $this->request->getHeader($key, $default);
    }
}