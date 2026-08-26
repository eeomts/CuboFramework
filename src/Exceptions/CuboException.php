<?php

namespace Cubo\Exceptions;

/**
 * Exceção base de todo o framework Cubo.
 *
 * Diferente do antigo Cubo_ErrorManager (que era Exception + handler ao mesmo
 * tempo), esta classe é uma exceção PURA: só carrega dados (mensagem, código,
 * causa anterior). Quem loga/redireciona é o {@see \Cubo\ErrorHandler}.
 *
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
}
