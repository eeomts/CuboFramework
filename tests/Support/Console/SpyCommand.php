<?php

namespace Cubo\Tests\Support\Console;

use Cubo\Console\Command;
use Cubo\Console\Input;
use Cubo\Console\Output;

final class SpyCommand implements Command
{
    public static function name(): string
    {
        return 'spy';
    }

    public static function description(): string
    {
        return 'Comando de teste';
    }

    public function handle(Input $input, Output $output): int
    {
        $output->line('spy: ' . implode(',', $input->arguments));

        return 0;
    }
}
