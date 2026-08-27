<?php

namespace Cubo\Console;

class Output
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout nulo usa a saida padrao; injetar em teste
     * @param resource|null $stderr nulo usa a saida de erro padrao
     */
    public function __construct($stdout = null, $stderr = null)
    {
        $this->stdout = $stdout ?? (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w'));
        $this->stderr = $stderr ?? (defined('STDERR') ? STDERR : fopen('php://stderr', 'w'));
    }

    public function line(string $text = ''): void
    {
        fwrite($this->stdout, $text . PHP_EOL);
    }

    public function error(string $text): void
    {
        fwrite($this->stderr, $text . PHP_EOL);
    }
}
