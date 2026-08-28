<?php

namespace Cubo\Console\Commands;

use Cubo\Console\Input;
use Cubo\Console\Kernel;
use Cubo\Console\Output;

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class MigrateStatusCommand extends MigrationCommand
{
    public static function name(): string
    {
        return 'migrate:status';
    }

    public static function description(): string
    {
        return 'Lista as migrations e o que ja rodou';
    }

    public function handle(Input $input, Output $output): int
    {
        $situacao = $this->migrator()->situacao();

        if ($situacao === []) {
            $output->line('Nenhuma migration encontrada.');

            return Kernel::EXIT_SUCCESS;
        }

        foreach ($situacao as $nome => $aplicada) {
            $output->line(($aplicada ? '  [x] ' : '  [ ] ') . $nome);
        }

        return Kernel::EXIT_SUCCESS;
    }
}
