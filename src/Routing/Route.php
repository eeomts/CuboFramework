<?php

/**
 * @package Cubo
 * @author mateus - github.com/eeomts
 *
 * Value object com o resultado do parse da URL.
 */

namespace Cubo\Routing;

readonly class Route
{
    /**
     * @param string $controller ja em camelCase; 'index' se vazio
     * @param string $method ja em camelCase; 'index' se vazio
     * @param array<string,string> $params pares chave=>valor extraidos da URL
     * @param list<string> $rawParams nomes de parametro na ordem original
     * @param string|null $module nulo em rota sem modulo
     * @param class-string|null $controllerClass FQCN; so rota declarada preenche
     * @param list<string> $middleware alem dos globais
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

    public function temModulo(): bool
    {
        return $this->module !== null;
    }

    /**
     * Decide quem invoca a action: rota declarada diz o metodo e o kernel chama;
     * na convencao o kernel chama index() e o controlador despacha.
     */
    public function ehDeclarada(): bool
    {
        return $this->controllerClass !== null;
    }
}
