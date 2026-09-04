<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 *
 * O que os primeiros segmentos da URL SIGNIFICAM.
 */

namespace Cubo\Routing;

readonly class RouteHead
{
    /**
     * @param string|null $module modulo, quando a app organiza a URL em modulos
     * @param string $controller controlador, ou feature conforme o mapper
     * @param string $method acao
     * @param int $consumed quantos segmentos a cabeca ocupou; os parametros comecam neste indice
     */
    public function __construct(
        public ?string $module,
        public string $controller,
        public string $method,
        public int $consumed
    ) {}
}
