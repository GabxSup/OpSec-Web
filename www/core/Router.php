<?php

class Router
{
    private array $routes = [];

    public function get(string $uri, array $action)
    {
        $this->routes['GET'][$uri] = $action;
    }

    public function post(string $uri, array $action)
    {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch(string $method, string $uri)
    {
        foreach ($this->routes[$method] ?? [] as $route => $action) {

            $pattern = preg_replace('#\{[a-zA-Z]+\}#', '([0-9]+)', $route);

            if (preg_match("#^$pattern$#", $uri, $matches)) {
                array_shift($matches);

                [$controller, $method] = $action;
                $instance = new $controller;

                call_user_func_array([$instance, $method], $matches);
                return;
            }
        }

        http_response_code(404);
        echo "404 Not Found";
    }
}
