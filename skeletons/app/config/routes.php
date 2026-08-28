<?php

/**
 * Tabela de rotas da aplicacao.
 *
 * Declare aqui so o que a convencao nao resolve: verbo HTTP proprio, URL que
 * nao espelha nome de classe, middleware por rota ou URL gerada por nome.
 * Todo caminho que nao casar com nada daqui continua caindo na convencao
 * /controlador/acao/param/valor -- as duas formas convivem.
 *
 * Para ligar, descomente `routes = config/routes.php` na secao [app] do
 * config.ini.
 */

declare(strict_types=1);

use Cubo\Routing\RouteCollection;

$rotas = new RouteCollection();

// Rota declarada CHAMA a action que ela declara. Na convencao, o kernel chama
// index() e o proprio controlador despacha -- por isso as duas convivem.
//
// $rotas->get('/clientes', App\Controllers\ClienteController::class, 'listar')
//     ->name('clientes.index');
//
// $rotas->post('/clientes', App\Controllers\ClienteController::class, 'salvar')
//     ->middleware(App\Middleware\CsrfMiddleware::class);
//
// $rotas->get('/clientes/{id}', App\Controllers\ClienteController::class, 'editar')
//     ->name('clientes.editar');

return $rotas;
