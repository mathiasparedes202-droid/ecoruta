<?php

namespace Core;

class Router
{
    private $request;
    private $response;
    private $routes = [];

    /**
     * Constructor del Router
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
     * Registra una ruta GET
     *
     * @param string $uri - URI de la ruta
     * @param callable|array $callback - Función o [Controller, método]
     * @param array $middlewares - Middlewares opcionales
     * @return void
     */
    public function get(string $uri, $callback, array $middlewares = [])
    {
        $this->addRoute('GET', $uri, $callback, $middlewares);
    }

    /**
     * Registra una ruta POST
     *
     * @param string $uri
     * @param callable|array $callback
     * @param array $middlewares
     * @return void
     */
    public function post(string $uri, $callback, array $middlewares = [])
    {
        $this->addRoute('POST', $uri, $callback, $middlewares);
    }

    /**
     * Registra una ruta PUT
     *
     * @param string $uri
     * @param callable|array $callback
     * @param array $middlewares
     * @return void
     */
    public function put(string $uri, $callback, array $middlewares = [])
    {
        $this->addRoute('PUT', $uri, $callback, $middlewares);
    }

    /**
     * Registra una ruta DELETE
     *
     * @param string $uri
     * @param callable|array $callback
     * @param array $middlewares
     * @return void
     */
    public function delete(string $uri, $callback, array $middlewares = [])
    {
        $this->addRoute('DELETE', $uri, $callback, $middlewares);
    }

    /**
     * Agrega una ruta al registro interno
     *
     * @param string $method
     * @param string $uri
     * @param callable|array $callback
     * @param array $middlewares
     * @return void
     */
    private function addRoute(string $method, string $uri, $callback, array $middlewares = [])
    {
        $this->routes[$method][$uri] = [
            'callback' => $callback,
            'middlewares' => $middlewares
        ];
    }

    /**
     * Despacha la petición a la ruta correspondiente
     *
     * @return void
     */
    public function dispatch()
    {
        $method = $this->request->getMethod();
        $uri = $this->request->getUri();

        error_log("Router dispatch: method=$method, uri=$uri");

        // Strip base path
        $base = '';
        if (strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        if ($uri === '') {
            $uri = '/';
        }

        error_log("After base strip: uri=$uri");

        $route = null;
        $params = [];

        // Buscar la ruta coincidente
        foreach ($this->routes[$method] as $routeUri => $r) {
            error_log("Checking route: $routeUri");
            // Convertimos /usuarios/{id} en regex
            $pattern = preg_replace('#\{([\w]+)\}#', '(?P<$1>[\w-]+)', $routeUri);
            $pattern = "#^$pattern$#";

            if (preg_match($pattern, $uri, $matches)) {
                $route = $r;

                // Extraemos solo los named params
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                break;
            }
        }

        error_log("Route found: " . ($route ? 'yes' : 'no'));

        if (!$route) {
            $this->response->error('Ruta no encontrada', 404);
        }

        // Ejecutar middlewares si existen
        foreach ($route['middlewares'] as $middleware) {
            $middlewareInstance = new $middleware();
            $middlewareInstance->handle($this->request, $this->response);
        }

        $callback = $route['callback'];

        // Si es un array [Controller, 'metodo']
        if (is_array($callback)) {
            [$controller, $methodName] = $callback;
            $controllerInstance = new $controller($this->request, $this->response);
            if (!method_exists($controllerInstance, $methodName)) {
                $this->response->error('Método del controlador no encontrado', 500);
            }
            $controllerInstance->$methodName($params);
        }
        // Si es callable simple
        elseif (is_callable($callback)) {
            call_user_func($callback, $this->request, $this->response);
        } else {
            $this->response->error('Callback de ruta inválido', 500);
        }
    }
}
