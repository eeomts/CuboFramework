<?php

namespace Cubo\Exceptions;

/**
 * Lançada quando uma rota declarada aponta para uma action que o controlador
 * não expõe.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class ActionNotFoundException extends CuboException
{
    public static function for(string $controller, string $action): self
    {
        return new self(
            "Action não encontrada: {$controller}::{$action}(). "
            . 'Deve ser um método público declarado no próprio controlador.',
            self::CODE_ACTION_MISSING,
        );
    }
}
