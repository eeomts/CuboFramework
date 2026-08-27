<?php

namespace Cubo\Exceptions;

/**
 * Lançada quando o nome digitado no CLI não corresponde a nenhum comando.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class CommandNotFoundException extends CuboException
{
    public static function for(string $name): self
    {
        return new self("Comando nao encontrado: {$name}");
    }
}
