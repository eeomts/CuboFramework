<?php

namespace Cubo\Console;

use Cubo\Console\Commands\BuildCommand;
use Cubo\Console\Commands\HelpCommand;
use Cubo\Console\Commands\InitCommand;
use Cubo\Console\Commands\MigrateCommand;
use Cubo\Console\Commands\MigrateRollbackCommand;
use Cubo\Console\Commands\MigrateStatusCommand;
use Cubo\Console\Commands\VersionCommand;
use Cubo\Exceptions\CommandNotFoundException;

final class CommandRegistry
{
    /** @var array<string,class-string<Command>|Command> */
    private array $commands = [];

    /**
     * @param list<class-string<Command>|Command> $commands
     */
    public function __construct(array $commands = [])
    {
        foreach ($commands as $command) {
            $this->add($command);
        }
    }

    /** Os comandos que acompanham o framework. */
    public static function default(?Paths $paths = null): self
    {
        $paths ??= Paths::detect();

        return new self([
            HelpCommand::class,
            VersionCommand::class,
            new BuildCommand($paths),
            new InitCommand($paths),
            MigrateCommand::class,
            MigrateRollbackCommand::class,
            MigrateStatusCommand::class,
        ]);
    }

    /**
     * @param class-string<Command>|Command $command classe (instanciada na hora)
     *                                               ou instancia ja pronta
     */
    public function add(string|Command $command): void
    {
        $this->commands[$command::name()] = $command;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * @throws CommandNotFoundException
     */
    public function get(string $name): Command
    {
        if (!$this->has($name)) {
            throw CommandNotFoundException::for($name);
        }

        $command = $this->commands[$name];

        return $command instanceof Command ? $command : new $command();
    }

    /**
     * Nome => descricao, em ordem alfabetica.
     *
     * @return array<string,string>
     */
    public function descriptions(): array
    {
        $all = [];

        foreach ($this->commands as $name => $class) {
            $all[$name] = $class::description();
        }

        ksort($all);

        return $all;
    }
}
