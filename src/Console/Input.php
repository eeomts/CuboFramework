<?php

namespace Cubo\Console;

readonly class Input
{
    /**
     * @param string $command nome do comando
     * @param list<string> $arguments 
     * @param array<string,string|bool> $options
     */
    public function __construct(
        public string $command = '',
        public array $arguments = [],
        public array $options = [],
    ) {}

    /**
     * @param list<string> $argv argv do PHP, com o nome do script em [0]
     */
    public static function fromArgv(array $argv): self
    {
        $command = '';
        $arguments = [];
        $options = [];

        foreach (array_slice($argv, 1) as $token) {
            if (str_starts_with($token, '-')) {
                $pair = explode('=', ltrim($token, '-'), 2);
                $options[$pair[0]] = $pair[1] ?? true;
                continue;
            }

            if ($command === '') {
                $command = $token;
                continue;
            }

            $arguments[] = $token;
        }

        return new self($command, $arguments, $options);
    }

    public function argument(int $position, ?string $default = null): ?string
    {
        return $this->arguments[$position] ?? $default;
    }

    public function option(string $name, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string ...$names): bool
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $this->options)) {
                return true;
            }
        }

        return false;
    }
}
