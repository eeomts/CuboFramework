<?php

namespace Cubo\Exceptions;

/**
 * Lançada quando a requisição aponta para um controlador que não existe.
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
