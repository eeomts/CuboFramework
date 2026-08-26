<?php

namespace Cubo\Tests\Support\Controllers;

use Cubo\Controller;

/**
 * Controlador que anota o ciclo de vida chamado pelo kernel.
 *
 * O nome importa: o teste de resolucao pela URL monta o nome da classe a partir
 * da rota ('spy' -> 'SpyController'), entao renomear isto quebra esse teste.
 */
class SpyController extends Controller
{
    /** @var list<string> */
    public array $calls = [];

    public function initialize(): void
    {
        $this->calls[] = 'initialize';
    }

    public function index(): void
    {
        $this->calls[] = 'index';
    }

    public function display(): void
    {
        $this->calls[] = 'display';

        parent::display();
    }
}
