<?php

namespace Cubo\Exceptions;

/**
 * Lançada quando uma feature opcional é usada sem o pacote que ela exige.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class MissingDependencyException extends CuboException
{
    /**
     * @param string $package pacote Composer que falta (ex: 'mpdf/mpdf')
     * @param string $usedBy classe do Cubo que precisa dele
     */
    public static function for(string $package, string $usedBy): self
    {
        return new self(
            "{$usedBy} precisa do pacote {$package}, que nao esta instalado. "
            . "Instale com: composer require {$package}"
        );
    }
}
