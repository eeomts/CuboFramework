<?php

namespace Cubo\Tests\Support\Controllers;

use Cubo\Controller;

/**
 * Imita o CoreController da app: no index() resolve qual modulo responde pela
 * requisicao e o guarda via setModule(). E o que faz o kernel renderizar a view
 * do MODULO em vez da dele.
 */
class CoreLikeController extends Controller
{
    public function index(): void
    {
        $this->setModule(new SpyController($this->getRoute()));
    }
}
