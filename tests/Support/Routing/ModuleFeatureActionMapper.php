<?php

/**
 * Mapper de 3 segmentos (modulo/feature/acao), so para teste.
 * O real e do Netflex: modulo e regra de produto, nao de framework.
 *
 * @package Cubo\Tests
 * @author Mateus - github.com/eeomts
 */

namespace Cubo\Tests\Support\Routing;

use Cubo\Routing\RouteHead;
use Cubo\Routing\SegmentMapper;

final class ModuleFeatureActionMapper implements SegmentMapper
{
    /**
     * @param list<string> $segments
     */
    public function head(array $segments): RouteHead
    {
        return new RouteHead(
            module: ($segments[0] ?? '') ?: null,
            controller: ($segments[1] ?? '') ?: 'index',
            method: ($segments[2] ?? '') ?: 'index',
            consumed: 3
        );
    }
}
