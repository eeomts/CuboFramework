<?php

namespace Cubo\Logging;

/**
 * Contrato minimo de log: quem precisa logar depende desta interface, nao de
 * "escrever em arquivo".
 *
 * So tem error(), o unico nivel usado hoje.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
interface LoggerInterface
{
    public function error(string $message): void;
}
