<?php

namespace Cubo\Tests\Support\Console;

use Cubo\Console\Command;
use Cubo\Console\Input;
use Cubo\Console\Output;
use Cubo\Exceptions\CuboException;

final class ExplodingCommand implements Command
{
    public static function name(): string
    {
        return 'explode';
    }

    public static function description(): string
    {
        return 'Sempre lanca CuboException';
    }

    public function handle(Input $input, Output $output): int
    {
        throw new CuboException('estourou de proposito');
    }
}
