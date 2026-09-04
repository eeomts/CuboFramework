<?php

namespace Cubo\Console;

use Cubo\Exceptions\CuboException;

final class Kernel
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;

    public function __construct(
        private readonly CommandRegistry $commands = new CommandRegistry(),
    ) {}

    public function run(Input $input, Output $output): int
    {
        $name = $this->resolveName($input);

        if (!$this->commands->has($name)) {
            $output->error("Comando nao encontrado: {$name}");
            $output->error("Use 'cubo help' para ver os comandos disponiveis.");

            return self::EXIT_FAILURE;
        }

        try {
            return $this->commands->get($name)->handle($input, $output);
        } catch (CuboException $e) {
            $output->error($e->getMessage());

            return self::EXIT_FAILURE;
        }
    }

    private function resolveName(Input $input): string
    {
        if ($input->command !== '') {
            return $input->command;
        }

        return $input->hasOption('version', 'v') ? 'version' : 'help';
    }
}
