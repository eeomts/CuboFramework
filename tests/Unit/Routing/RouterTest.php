<?php

namespace Cubo\Tests\Unit\Routing;

use Cubo\Routing\ControllerActionMapper;
use Cubo\Routing\Route;
use Cubo\Routing\RouteHead;
use Cubo\Http\Request;
use Cubo\Routing\Router;
use Cubo\Tests\Support\Routing\ModuleFeatureActionMapper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

#[CoversClass(Router::class)]
#[CoversClass(Route::class)]
#[CoversClass(RouteHead::class)]
#[CoversClass(ControllerActionMapper::class)]
final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    // --- transformMethod / toCamelCase (puros, sem globais) ---

    public function testTransformMethodConverteHifenEmCamelCase(): void
    {
        $this->assertSame('gridMenusFilho', $this->router->transformMethod('grid-menus-filho'));
    }

    public function testTransformMethodComControllerBarraMetodo(): void
    {
        // so o controlador recebe ucfirst; o metodo vira camelCase
        $this->assertSame('Financeiro/gridMenus', $this->router->transformMethod('financeiro/grid-menus'));
    }

    public function testTransformMethodSemHifenMantemPalavra(): void
    {
        $this->assertSame('index', $this->router->transformMethod('index'));
    }

    public function testTransformMethodUmCaractereRecebeUcfirst(): void
    {
        // quirk do v1: segmento de 1 caractere recebe ucfirst
        $this->assertSame('A', $this->router->transformMethod('a'));
    }

    // --- ControllerActionMapper: o padrao de 2 segmentos ---

    public function testMapperPadraoLeControllerEAcaoESemModulo(): void
    {
        $head = (new ControllerActionMapper())->head(['financeiro', 'gridMenus', 'id', '5']);

        $this->assertNull($head->module);
        $this->assertSame('financeiro', $head->controller);
        $this->assertSame('gridMenus', $head->method);
        $this->assertSame(2, $head->consumed);
    }

    public function testMapperPadraoCaiEmIndexComSegmentosVazios(): void
    {
        $head = (new ControllerActionMapper())->head([]);

        $this->assertSame('index', $head->controller);
        $this->assertSame('index', $head->method);
    }

    // --- parseUrl (recebe a Request e o basePath injetado) ---

    /** A rota sai da Request, entao o teste nao precisa mexer em $_SERVER. */
    private function requisicao(string $uri): Request
    {
        return new Request(server: ['REQUEST_URI' => $uri]);
    }

    public function testParseUrlMontaRotaComControllerMetodoEParams(): void
    {
        $requisicao = $this->requisicao('/app/financeiro/grid-menus/id/5');

        $route = (new Router(basePath: '/app/'))->parseUrl($requisicao);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('financeiro', $route->controller);
        $this->assertSame('gridMenus', $route->method);
        $this->assertSame(['id' => '5'], $route->params);
        $this->assertSame(['id'], $route->rawParams);
        $this->assertNull($route->module);
        $this->assertFalse($route->temModulo());
    }

    public function testParseUrlUsaIndexQuandoUrlVazia(): void
    {
        $requisicao = $this->requisicao('/app/');

        $route = (new Router(basePath: '/app/'))->parseUrl($requisicao);

        $this->assertSame('index', $route->controller);
        $this->assertSame('index', $route->method);
    }

    public function testAppNaRaizComPortaNaoVazaOHostParaDentroDaRota(): void
    {
        // regressao: o parse antigo subtraia o host de SERVER_NAME . REQUEST_URI,
        // e SERVER_NAME vem sem a porta -- entao 'localhost' virava controlador
        $requisicao = $this->requisicao('/');

        $route = (new Router(basePath: '/'))->parseUrl($requisicao);

        $this->assertSame('index', $route->controller);
        $this->assertSame('index', $route->method);
    }

    public function testQueryStringNaoEntraNaRota(): void
    {
        $requisicao = $this->requisicao('/app/financeiro/grid-menus?busca=x&pagina=2');

        $route = (new Router(basePath: '/app/'))->parseUrl($requisicao);

        $this->assertSame('financeiro', $route->controller);
        $this->assertSame('gridMenus', $route->method);
    }

    public function testBasePathSemBarraFinalTambemCasa(): void
    {
        $requisicao = $this->requisicao('/app');

        $route = (new Router(basePath: '/app'))->parseUrl($requisicao);

        $this->assertSame('index', $route->controller);
    }

    /**
     * O mapper decide o significado E onde os parametros comecam: com modulo,
     * o par id/7 esta no indice 3, nao no 2.
     */
    public function testParseUrlComMapperDeModuloLeTresSegmentos(): void
    {
        $requisicao = $this->requisicao('/app/produtividade/tarefa/minhas/id/7');

        $route = (new Router(new ModuleFeatureActionMapper(), '/app/'))->parseUrl($requisicao);

        $this->assertSame('produtividade', $route->module);
        $this->assertSame('tarefa', $route->controller);
        $this->assertSame('minhas', $route->method);
        $this->assertSame(['id' => '7'], $route->params);
        $this->assertTrue($route->temModulo());
    }

    /**
     * __CONTROLLER__ e __ACTION__ eram definidas como efeito colateral do parseUrl.
     */
    public function testParseUrlNaoDefineMaisConstantesGlobais(): void
    {
        $requisicao = $this->requisicao('/app/financeiro/grid-menus');

        (new Router(basePath: '/app/'))->parseUrl($requisicao);

        $this->assertFalse(defined('__CONTROLLER__'));
        $this->assertFalse(defined('__ACTION__'));
    }

    // --- getNameModule (agora puro: recebe a rota, nao le global) ---

    public function testGetNameModuleRetornaControllerEmTitleCaseSemModulo(): void
    {
        $route = new Route('financeiro', 'index');

        $this->assertSame('Financeiro', $this->router->getNameModule($route));
    }

    public function testGetNameModulePreferOModuloQuandoARotaTemUm(): void
    {
        $route = new Route('tarefa', 'minhas', [], [], 'produtividade');

        $this->assertSame('Produtividade', $this->router->getNameModule($route));
    }
}
