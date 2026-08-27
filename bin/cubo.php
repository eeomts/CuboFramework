#!/usr/bin/env php
<?php

use Cubo\Console\CommandRegistry;
use Cubo\Console\Input;
use Cubo\Console\Kernel;
use Cubo\Console\Output;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

exit((new Kernel(CommandRegistry::default()))->run(
    Input::fromArgv($argv),
    new Output()
));
