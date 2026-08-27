<?php

namespace Cubo\Console\Commands;

use Cubo\Console\Command;
use Cubo\Console\CommandRegistry;
use Cubo\Console\Input;
use Cubo\Console\Kernel;
use Cubo\Console\Output;
use Cubo\Cubo;

final class HelpCommand implements Command
{
    private readonly CommandRegistry $commands;

    public function __construct(?CommandRegistry $commands = null)
    {
        $this->commands = $commands ?? CommandRegistry::default();
    }

    public static function name(): string
    {
        return 'help';
    }

    public static function description(): string
    {
        return 'Lista os comandos disponiveis';
    }

    public function handle(Input $input, Output $output): int
    {
        $output->line('Cubo ' . Cubo::VERSION);
        $output->line();
        $output->line('Uso: cubo <comando> [argumentos] [--opcoes]');
        $output->line();
        $output->line('Comandos:');

        foreach ($this->commands->descriptions() as $name => $description) {
            $output->line('  ' . str_pad($name, 18) . $description);
        }

        return Kernel::EXIT_SUCCESS;
    }
}
