<?php

/**
 * Roteador de URL.
 * Traca as rotas e define controlador, metodo e parametros a partir da REQUEST_URI.
 *
 * @package Cubo
 * @author v1: Joao / v1.1: Cristiano
 * @author v2: Mateus - github.com/eeomts
 */

namespace Cubo\Routing;

use Cubo\Config;
use Cubo\Http\Request;

class Router
{
    public const ROUTES = 'routes';

    /**
     * @param SegmentMapper $mapper padrao: controlador/acao
     * @param string|null $basePath subpasta onde a app esta montada; nulo tira do config.ini
     * @param RouteCollection|null $routes consultada antes da convencao
     */
    public function __construct(
        private SegmentMapper $mapper = new ControllerActionMapper(),
        private ?string $basePath = null,
        private ?RouteCollection $routes = null,
    ) {}

    /** A tabela de rotas tem precedencia; sem rota que case, vale o SegmentMapper. */
    public function parseUrl(Request $request): Route
    {
        $path = $this->requestPath($request);

        # a tabela casa o caminho CRU: '/grid-menus' nunca casaria contra o segmento ja em camelCase
        $declarada = $this->tabela()?->match($path, $request->method());

        if ($declarada !== null) {
            return $declarada;
        }

        # cada segmento vira camelCase (grid-menus -> gridMenus)
        $parsed = [];
        foreach (explode('/', $path) as $segment) {
            $parsed[] = $this->toCamelCase($segment);
        }

        $head = $this->mapper->head($parsed);
        [$params, $rawParams] = $this->parseParams($parsed, $head->consumed);

        return new Route(
            $head->controller,
            $head->method,
            $params,
            $rawParams,
            $head->module
        );
    }

    /** Sem a subpasta de montagem e sem query string; o dominio nao participa. */
    private function requestPath(Request $request): string
    {
        $path = $request->path();

        $base = rtrim($this->resolveBasePath(), '/') . '/';
        $normalizado = rtrim($path, '/') . '/';

        if (str_starts_with($normalizado, $base)) {
            $path = substr($normalizado, strlen($base));
        }

        return trim($path, '/');
    }

    /** Tabela injetada tem precedencia; sem ela vale a do [app] routes. */
    private function tabela(): ?RouteCollection
    {
        if ($this->routes !== null) {
            return $this->routes;
        }

        $declarada = Config::getInstance()->getConfig(self::ROUTES);

        return $declarada instanceof RouteCollection ? $declarada : null;
    }

    private function resolveBasePath(): string
    {
        if ($this->basePath !== null) {
            return $this->basePath;
        }

        $host = Config::getInstance()->getConfig('ini.cubo.host');

        return is_string($host) ? (string) (parse_url($host, PHP_URL_PATH) ?: '/') : '/';
    }

    /**
     * Pares chave/valor que vem depois da cabeca da rota.
     *
     * @param list<string> $segments
     * @param int $from indice do primeiro segmento de parametro
     * @return array{0: array<string,string>, 1: list<string>} [params, rawParams]
     */
    private function parseParams(array $segments, int $from): array
    {
        $params = [];
        $rawParams = [];

        for ($i = $from; $i < count($segments); $i += 2) {
            $rawParams[] = $segments[$i];

            $key = strtolower($segments[$i]);
            $value = $segments[$i + 1] ?? '';

            if (isset($params[$key])) {
                $params[$key] .= "_{$value}";
            } else {
                $params[$key] = $value;
            }
        }

        return [$params, $rawParams];
    }

    /** grid-menus-filho -> gridMenusFilho ; ctrl/grid-menus -> Ctrl/gridMenus */
    public function transformMethod(string $value): string
    {
        $parts = explode('/', $value);
        if (count($parts) > 1) {
            $value = ucfirst($parts[0]) . '/' . $parts[1];
        }

        return $this->toCamelCase($value);
    }

    /** @param Route $route de onde sai o nome: modulo, ou controlador sem modulo */
    public function getNameModule(Route $route): string
    {
        return ucwords(strtolower($route->module ?? $route->controller));
    }

    /** Host + modulo, para exportacao/impressao. */
    public function getUrlExport(Route $route): string
    {
        return Config::getInstance()->getConfig('ini.cubo.host') . $this->getNameModule($route);
    }

    /** "com-hifen" em camelCase: o primeiro pedaco fica como esta, os seguintes recebem ucfirst. */
    private function toCamelCase(string $value): string
    {
        $parts = explode('-', $value);

        if (count($parts) > 1) {
            $out = '';
            foreach ($parts as $i => $part) {
                $out .= $i > 0 ? ucfirst($part) : $part;
            }
            return $out;
        }

        # sem hifen: ucfirst so quando o 2o caractere e vazio/'0'
        $second = $value[1] ?? '';
        return ($second === '' || $second === '0') ? ucfirst($value) : $value;
    }
}
