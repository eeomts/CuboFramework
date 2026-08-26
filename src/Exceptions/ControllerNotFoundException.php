<?php

namespace Cubo\Exceptions;

/**
 * Lançada quando a requisição aponta para um controlador que não existe.
 *
 * Substitui: throw new Cubo_ErrorManager('Controlador não encontado', 107)
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class ControllerNotFoundException extends CuboException
{
    /**
     * Named constructor: deixa o call site legível e o código correto garantido.
     */
    public static function for(string $controller): self
    {
        return new self(
            "Controlador não encontrado: {$controller}",
            self::CODE_CONTROLLER_MISSING,
        );
    }
}
