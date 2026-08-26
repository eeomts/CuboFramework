<?php

namespace Cubo\Logging;

/**
 * Contrato mínimo de log do framework.
 *
 * Existe para inverter a dependência: quem precisa logar (ex.: {@see \Cubo\ErrorHandler})
 * depende desta interface, não de "escrever em arquivo".
 *
 * Comeca só com error() — o único nível usado no framework hoje. Novos níveis
 * (warning/info/debug) entram aqui quando houver quem os chame.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
interface LoggerInterface
{
    public function error(string $message): void;
}
