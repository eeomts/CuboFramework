<?php

namespace Cubo\Console\Commands;

use Cubo\Console\Input;
use Cubo\Console\Kernel;
use Cubo\Console\Output;

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class MigrateCommand extends MigrationCommand
{
    public static function name(): string
    {
        return 'migrate';
    }

    public static function description(): string
    {
        return 'Aplica as migrations pendentes';
    }

    public function handle(Input $input, Output $output): int
    {
        $aplicadas = $this->migrator()->subir();

        if ($aplicadas === []) {
            $output->line('Nada a aplicar: o banco ja esta em dia.');

            return Kernel::EXIT_SUCCESS;
        }

        foreach ($aplicadas as $nome) {
            $output->line('  aplicada  ' . $nome);
        }

        $output->line(count($aplicadas) . ' migration(s) aplicada(s).');

        return Kernel::EXIT_SUCCESS;
    }
}
