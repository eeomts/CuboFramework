<?php

namespace Cubo\Tests\Unit\Routing;

use Cubo\Routing\RouteCollection;
use Cubo\Routing\RouteDefinition;
use Cubo\Tests\Support\Controllers\SpyController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteCollection::class)]
#[CoversClass(RouteDefinition::class)]
final class RouteCollectionTest extends TestCase
{
    private RouteCollection $rotas;

    protected function setUp(): void
    {
        $this->rotas = new RouteCollection();
    }

    public function testCasaCaminhoLiteral(): void
    {
        $this->rotas->get('/clientes', SpyController::class, 'listar');

        $rota = $this->rotas->match('clientes', 'GET');

        $this->assertNotNull($rota);
        $this->assertSame(SpyController::class, $rota->controllerClass);
        $this->assertSame('listar', $rota->method);
        $this->assertTrue($rota->ehDeclarada());
    }

    public function testNaoCasaVerboDiferente(): void
    {
        $this->rotas->get('/clientes', SpyController::class, 'listar');

        $this->assertNull($this->rotas->match('clientes', 'POST'));
    }

    /** O ponto da tabela: mesmo caminho, verbos diferentes, actions diferentes. */
    public function testMesmoCaminhoComVerbosDiferentesVaiParaActionsDiferentes(): void
    {
        $this->rotas->get('/clientes', SpyController::class, 'listar');
        $this->rotas->post('/clientes', SpyController::class, 'salvar');

        $this->assertSame('listar', $this->rotas->match('clientes', 'GET')->method);
        $this->assertSame('salvar', $this->rotas->match('clientes', 'POST')->method);
    }

    public function testExtraiParametroNomeado(): void
    {
        $this->rotas->get('/clientes/{id}', SpyController::class, 'editar');

        $rota = $this->rotas->match('clientes/7', 'GET');

        $this->assertSame(['id' => '7'], $rota->params);
        $this->assertSame(['id'], $rota->rawParams);
    }

    public function testExtraiVariosParametros(): void
    {
        $this->rotas->get('/clientes/{cliente}/pedidos/{pedido}', SpyController::class, 'verPedido');

        $rota = $this->rotas->match('clientes/7/pedidos/42', 'GET');

        $this->assertSame(['cliente' => '7', 'pedido' => '42'], $rota->params);
    }

    /** Parametro nao atravessa barra: /clientes/{id} nao casa /clientes/7/extra. */
    public function testParametroNaoAtravessaBarra(): void
    {
        $this->rotas->get('/clientes/{id}', SpyController::class, 'editar');

        $this->assertNull($this->rotas->match('clientes/7/extra', 'GET'));
    }

    public function testCasaComOuSemBarraNasPontas(): void
    {
        $this->rotas->get('clientes', SpyController::class, 'listar');

        $this->assertNotNull($this->rotas->match('/clientes/', 'GET'));
        $this->assertNotNull($this->rotas->match('clientes', 'GET'));
    }

    /** URL com hifen nao passa por camelCase: a tabela casa o caminho cru. */
    public function testCasaCaminhoComHifen(): void
    {
        $this->rotas->get('/grid-menus', SpyController::class, 'grid');

        $this->assertNotNull($this->rotas->match('grid-menus', 'GET'));
    }

    public function testAOrdemDeDeclaracaoDecide(): void
    {
        $this->rotas->get('/clientes/novo', SpyController::class, 'formulario');
        $this->rotas->get('/clientes/{id}', SpyController::class, 'editar');

        $this->assertSame('formulario', $this->rotas->match('clientes/novo', 'GET')->method);
        $this->assertSame('editar', $this->rotas->match('clientes/7', 'GET')->method);
    }

    public function testSemRotaDeclaradaDevolveNull(): void
    {
        $this->rotas->get('/clientes', SpyController::class, 'listar');

        $this->assertNull($this->rotas->match('produtos', 'GET'));
    }

    public function testMiddlewareDaRotaViajaNaRota(): void
    {
        $this->rotas->post('/clientes', SpyController::class, 'salvar')
            ->middleware('MW1', 'MW2');

        $this->assertSame(['MW1', 'MW2'], $this->rotas->match('clientes', 'POST')->middleware);
    }

    public function testNomeDaRotaViajaNaRota(): void
    {
        $this->rotas->get('/clientes', SpyController::class, 'listar')->name('clientes.index');

        $this->assertSame('clientes.index', $this->rotas->match('clientes', 'GET')->name);
    }

    // --- geracao de URL por nome ---

    public function testUrlDeRotaNomeadaSemParametro(): void
    {
        $this->rotas->get('/clientes', SpyController::class, 'listar')->name('clientes.index');

        $this->assertSame('/clientes', $this->rotas->url('clientes.index'));
    }

    public function testUrlDeRotaNomeadaComParametro(): void
    {
        $this->rotas->get('/clientes/{id}', SpyController::class, 'editar')->name('clientes.editar');

        $this->assertSame('/clientes/7', $this->rotas->url('clientes.editar', ['id' => 7]));
    }

    public function testUrlEscapaOValorDoParametro(): void
    {
        $this->rotas->get('/busca/{termo}', SpyController::class, 'buscar')->name('busca');

        $this->assertSame('/busca/a%2Fb', $this->rotas->url('busca', ['termo' => 'a/b']));
    }

    public function testUrlSemOParametroExigidoEstoura(): void
    {
        $this->rotas->get('/clientes/{id}', SpyController::class, 'editar')->name('clientes.editar');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/exige o parametro 'id'/");

        $this->rotas->url('clientes.editar');
    }

    public function testUrlDeRotaInexistenteEstoura(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nao existe/');

        $this->rotas->url('nao.existe');
    }
}
