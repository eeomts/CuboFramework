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
    /**
     * Códigos herdados do legado (Cubo_ErrorManager) mantidos por
     * compatibilidade com a página de erro (error/index/code/{code}).
     */
    public const CODE_CONTROLLER_MISSING = 107;
    public const CODE_TEMPLATE_MISSING = 108;

    /** Novo no 2.1 (nao vem do legado): rota declarada apontando para action inexistente. */
    public const CODE_ACTION_MISSING = 109;
}
