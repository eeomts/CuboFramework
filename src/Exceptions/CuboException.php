<?php

namespace Cubo\Exceptions;

/**
 * Exceção base de todo o framework Cubo.
 * @package Cubo
 * @author v1: João (Cubo_ErrorManager)
 * @author v2: Mateus - github.com/eeomts
 */
class CuboException extends \RuntimeException
{
    public const CODE_CONTROLLER_MISSING = 107;
    public const CODE_TEMPLATE_MISSING = 108;

    # Novos no 2.1
    public const CODE_ACTION_MISSING = 109;
    public const CODE_SCHEMA_CONVENTION = 110;
}
