<?php

namespace Cubo\Console\Commands;

use Cubo\Console\Command;
use Cubo\Console\Input;
use Cubo\Console\Output;
use Cubo\Console\Kernel;
use Cubo\Cubo;

final class VersionCommand implements Command
{
    public static function name(): string
    {
        return 'version';
    }

    public static function description(): string
    {
        return 'Mostra a versao do Cubo';
    }

    public function handle(Input $input, Output $output): int
    {
        $output->line('Cubo ' . Cubo::VERSION);

        return Kernel::EXIT_SUCCESS;
    }
}
