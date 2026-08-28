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
    /**
     * @param SegmentMapper $mapper diz o que os segmentos de cabeca significam.
     *                              Sem argumento, vale o padrao controlador/acao.
     * @param string|null $basePath subpasta onde a app esta montada ('/app/').
     *                              Nulo tira do host declarado no config.ini.
     */
    public function __construct(
        private SegmentMapper $mapper = new ControllerActionMapper(),
        private ?string $basePath = null,
    ) {}

    /**
     * Monta a rota a partir do caminho da requisicao.
     * Quem da significado aos primeiros segmentos e o SegmentMapper.
     */
    public function parseUrl(Request $request): Route
    {
        // cada segmento vira camelCase (ex: grid-menus -> gridMenus)
        $parsed = [];
        foreach (explode('/', $this->requestPath($request)) as $segment) {
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

    /**
     * Caminho da requisicao sem a subpasta de montagem e sem query string.
     * O dominio nao participa: rota e caminho, nao host.
     */
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

    /**
     * Transforma um path de metodo em camelCase, preservando o controlador.
     * ex: grid-menus-filho -> gridMenusFilho ; ctrl/grid-menus -> Ctrl/gridMenus
     */
    public function transformMethod(string $value): string
    {
        $parts = explode('/', $value);
        if (count($parts) > 1) {
            $value = ucfirst($parts[0]) . '/' . $parts[1];
        }

        return $this->toCamelCase($value);
    }

    /**
     * Nome do modulo da rota, em Title Case.
     *
     * @param Route $route rota de onde sai o nome (modulo, ou controlador sem modulo)
     */
    public function getNameModule(Route $route): string
    {
        return ucwords(strtolower($route->module ?? $route->controller));
    }

    /**
     * URL base para exportacao/impressao (host + modulo).
     */
    public function getUrlExport(Route $route): string
    {
        return Config::getInstance()->getConfig('ini.cubo.host') . $this->getNameModule($route);
    }

    /**
     * Converte um segmento "com-hifen" em camelCase.
     * Primeiro pedaco fica como esta; os seguintes recebem ucfirst.
     * Sem hifen: so faz ucfirst quando nao ha 2o caractere (quirk mantido).
     */
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

        // sem hifen: quirk do v1 -> ucfirst so quando 2o caractere e vazio/'0'
        $second = $value[1] ?? '';
        return ($second === '' || $second === '0') ? ucfirst($value) : $value;
    }
}
