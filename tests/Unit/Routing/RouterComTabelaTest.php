<?php

namespace Cubo\Tests\Unit\Routing;

use Cubo\Http\Request;
use Cubo\Routing\RouteCollection;
use Cubo\Routing\Router;
use Cubo\Tests\Support\Controllers\LojaController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A tabela e a convencao convivendo: declarada vence, o resto cai na convencao.
 */
#[CoversClass(Router::class)]
final class RouterComTabelaTest extends TestCase
{
    private function requisicao(string $uri, string $verbo = 'GET'): Request
    {
        return new Request(server: ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => $verbo]);
    }

    public function testRotaDeclaradaVenceAConvencao(): void
    {
        $tabela = new RouteCollection();
        $tabela->get('/clientes', LojaController::class, 'listar');

        $rota = (new Router(basePath: '/', routes: $tabela))
            ->parseUrl($this->requisicao('/clientes'));

        $this->assertTrue($rota->ehDeclarada());
        $this->assertSame(LojaController::class, $rota->controllerClass);
        $this->assertSame('listar', $rota->method);
    }

    public function testCaminhoNaoDeclaradoCaiNaConvencao(): void
    {
        $tabela = new RouteCollection();
        $tabela->get('/clientes', LojaController::class, 'listar');

        $rota = (new Router(basePath: '/', routes: $tabela))
            ->parseUrl($this->requisicao('/financeiro/grid-menus'));

        $this->assertFalse($rota->ehDeclarada());
        $this->assertSame('financeiro', $rota->controller);
        $this->assertSame('gridMenus', $rota->method);
    }

    /** Verbo que a tabela nao declara tambem cai na convencao. */
    public function testVerboNaoDeclaradoCaiNaConvencao(): void
    {
        $tabela = new RouteCollection();
        $tabela->get('/clientes', LojaController::class, 'listar');

        $rota = (new Router(basePath: '/', routes: $tabela))
            ->parseUrl($this->requisicao('/clientes', 'POST'));

        $this->assertFalse($rota->ehDeclarada());
        $this->assertSame('clientes', $rota->controller);
    }

    public function testSemTabelaTudoSegueNaConvencao(): void
    {
        $rota = (new Router(basePath: '/'))->parseUrl($this->requisicao('/clientes/editar'));

        $this->assertFalse($rota->ehDeclarada());
        $this->assertSame('clientes', $rota->controller);
        $this->assertSame('editar', $rota->method);
    }

    public function testTabelaRespeitaOBasePathDaApp(): void
    {
        $tabela = new RouteCollection();
        $tabela->get('/clientes/{id}', LojaController::class, 'editar');

        $rota = (new Router(basePath: '/blog/', routes: $tabela))
            ->parseUrl($this->requisicao('/blog/clientes/7'));

        $this->assertTrue($rota->ehDeclarada());
        $this->assertSame(['id' => '7'], $rota->params);
    }

    /** A query string nao participa do casamento. */
    public function testQueryStringNaoAtrapalhaOCasamento(): void
    {
        $tabela = new RouteCollection();
        $tabela->get('/clientes', LojaController::class, 'listar');

        $rota = (new Router(basePath: '/', routes: $tabela))
            ->parseUrl($this->requisicao('/clientes?pagina=2'));

        $this->assertTrue($rota->ehDeclarada());
    }

    /** _method num POST faz a rota PUT declarada casar. */
    public function testVerboSpoofadoCasaARotaDeclarada(): void
    {
        $tabela = new RouteCollection();
        $tabela->put('/clientes/{id}', LojaController::class, 'salvar');

        $request = new Request(
            server: ['REQUEST_URI' => '/clientes/7', 'REQUEST_METHOD' => 'POST'],
            post: ['_method' => 'PUT']
        );

        $rota = (new Router(basePath: '/', routes: $tabela))->parseUrl($request);

        $this->assertTrue($rota->ehDeclarada());
        $this->assertSame('salvar', $rota->method);
    }
}
