<?php

namespace Cubo\Console;

use Cubo\Console\Commands\HelpCommand;
use Cubo\Console\Commands\VersionCommand;
use Cubo\Exceptions\CommandNotFoundException;

final class CommandRegistry
{
    /** @var array<string,class-string<Command>> */
    private array $commands = [];

    /**
     * @param list<class-string<Command>> $commands
     */
    public function __construct(array $commands = [])
    {
        foreach ($commands as $class) {
            $this->add($class);
        }
    }

    /** Os comandos que acompanham o framework. */
    public static function default(): self
    {
        return new self([
            HelpCommand::class,
            VersionCommand::class,
        ]);
    }

    /**
     * @param class-string<Command> $class
     */
    public function add(string $class): void
    {
        $this->commands[$class::name()] = $class;
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

        $class = $this->commands[$name];

        return new $class();
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
