<?php

namespace Cubo\Tests\Support\Controllers;

use Cubo\Controller;

/**
 * Controlador com actions nomeadas, para exercitar a tabela de rotas.
 */
class LojaController extends Controller
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

    public function listar(): void
    {
        $this->calls[] = 'listar';
    }

    public function salvar(): void
    {
        $this->calls[] = 'salvar';
    }

    public function editar(): void
    {
        $this->calls[] = 'editar:' . ($this->_route->params['id'] ?? '?');
    }

    protected function interna(): void
    {
        $this->calls[] = 'interna';
    }

    /** Imprime a trilha sem registra-la, para o teste de ponta a ponta enxergar. */
    public function display(): void
    {
        echo '<b>' . implode(',', $this->calls) . '</b>';

        parent::display();
    }
}
