<?php

namespace Cubo\Console;

interface Command
{
    public static function name(): string;

    public static function description(): string;

    /**
     * @return int codigo de saida: 0 sucesso
     */
    public function handle(Input $input, Output $output): int;
}
