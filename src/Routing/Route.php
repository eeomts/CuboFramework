<?php

/**
 * @package Cubo
 * @author mateus - github.com/eeomts
 *
 * readonly class Route: value object com o resultado do parse da URL.
 * Substitui o array associativo ['controller','method','request','parans_for_cast']
 * que o Cubo_Router::parseUrl() do v1 devolvia.
 */

namespace Cubo\Routing;

readonly class Route
{
    /**
     * @param string $controller nome do controlador (ja em camelCase; 'index' se vazio)
     * @param string $method nome da action (ja em camelCase; 'index' se vazio)
     * @param array<string,string> $params pares chave=>valor extraidos da URL
     * @param list<string> $rawParams nomes de parametro na ordem original (ex-parans_for_cast)
     * @param string|null $module modulo, quando o SegmentMapper da app resolve um;
     *                            nulo em rota sem modulo (ex: /login)
     * @param class-string|null $controllerClass preenchido so por rota declarada,
     *                            que diz o controlador em FQCN em vez de deixar
     *                            o kernel montar o nome a partir da URL
     * @param list<string> $middleware middleware desta rota, alem dos globais
     * @param string|null $name nome da rota declarada, para gerar URL
     */
    public function __construct(
        public string $controller,
        public string $method,
        public array $params = [],
        public array $rawParams = [],
        public ?string $module = null,
        public ?string $controllerClass = null,
        public array $middleware = [],
        public ?string $name = null
    ) {}

    /** Rota dentro de um modulo, e nao no nivel de cima da app. */
    public function temModulo(): bool
    {
        return $this->module !== null;
    }

    /**
     * Veio da tabela de rotas, e nao da convencao.
     *
     * E o que decide quem invoca a action: rota declarada disse qual metodo
     * chamar, entao o kernel chama; na convencao o kernel segue chamando
     * index() e o proprio controlador despacha.
     */
    public function ehDeclarada(): bool
    {
        return $this->controllerClass !== null;
    }
}
