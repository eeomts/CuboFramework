<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 *
 * Mapeamento padrao: segmento 0 = controlador, 1 = acao.
 *
 */

namespace Cubo\Routing;

final class ControllerActionMapper implements SegmentMapper
{
    /**
     * @param list<string> $segments
     */
    public function head(array $segments): RouteHead
    {
        return new RouteHead(
            module: null,
            controller: ($segments[0] ?? '') ?: 'index',
            method: ($segments[1] ?? '') ?: 'index',
            consumed: 2
        );
    }
}
