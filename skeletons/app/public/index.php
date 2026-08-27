<?php

declare(strict_types=1);

use Cubo\Cubo;

$raiz = dirname(__DIR__);

require $raiz . '/vendor/autoload.php';

(new Cubo(appRoot: $raiz))->run();
