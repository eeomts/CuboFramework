<?php

use Cubo\Routing\RouteCollection;
use Cubo\Tests\Support\Controllers\LojaController;

$rotas = new RouteCollection();

$rotas->get('/loja/{id}', LojaController::class, 'editar')->name('loja.editar');
$rotas->post('/loja', LojaController::class, 'salvar');

return $rotas;
