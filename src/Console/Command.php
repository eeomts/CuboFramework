<?php

namespace Cubo\Console;

interface Command
{
    /** Nome invocavel, ex.: 'init' ou 'make:controller'. */
    public static function name(): string;

    /** Uma linha, exibida pelo help. */
    public static function description(): string;

    /**
     * @return int codigo de saida: 0 sucesso, qualquer outro valor falha
     */
    public function handle(Input $input, Output $output): int;
}
