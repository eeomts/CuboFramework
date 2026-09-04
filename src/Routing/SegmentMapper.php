<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 *
 * Contrato: dados os segmentos da URL, o que eles significam.
 */

namespace Cubo\Routing;

interface SegmentMapper
{
    /** @param list<string> $segments ja em camelCase */
    public function head(array $segments): RouteHead;
}
