<?php

namespace Cubo\Console\Commands;

use Cubo\Console\Input;
use Cubo\Console\Kernel;
use Cubo\Console\Output;

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class MigrateRollbackCommand extends MigrationCommand
{
    public static function name(): string
    {
        return 'migrate:rollback';
    }

    public static function description(): string
    {
        return 'Desfaz o ultimo lote de migrations';
    }

    public function handle(Input $input, Output $output): int
    {
        $desfeitas = $this->migrator()->desfazer();

        if ($desfeitas === []) {
            $output->line('Nada a desfazer.');

            return Kernel::EXIT_SUCCESS;
        }

        foreach ($desfeitas as $nome) {
            $output->line('  desfeita  ' . $nome);
        }

        $output->line(count($desfeitas) . ' migration(s) desfeita(s).');

        return Kernel::EXIT_SUCCESS;
    }
}
