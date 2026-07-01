<?php
namespace App;

final class Router
{
    /** @var array<int, array{method:string, regex:string, keys:array<int,string>, handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $keys = [];
        $regex = preg_replace_callback('#:([a-zA-Z_]+)#', function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '([^/]+)';
        }, $path);
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH) ?? '/', '/');
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['regex'], $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($route['keys'], $matches) ?: [];
                return ($route['handler'])($params);
            }
        }
        throw new NotFoundException("No route for {$method} {$uri}");
    }
}
