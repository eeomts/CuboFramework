#!/usr/bin/env php
<?php

use Cubo\Console\CommandRegistry;
use Cubo\Console\Input;
use Cubo\Console\Kernel;
use Cubo\Console\Output;
use Cubo\Console\Paths;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

exit((new Kernel(CommandRegistry::default(new Paths(dirname(__DIR__)))))->run(
    Input::fromArgv($argv),
    new Output()
));
