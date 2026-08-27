<?php

declare(strict_types=1);

namespace App\Controllers;

use Cubo\Controller;

final class IndexController extends Controller
{
    public function index(): void
    {
        $this->getView()->addParam('titulo', 'Cubo');
        $this->getView()->addParam('mensagem', 'Projeto criado com cubo init.');
    }
}
