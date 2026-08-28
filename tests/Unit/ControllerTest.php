<?php

namespace Cubo\Tests\Unit;

use Cubo\Controller;
use Cubo\Http\Request;
use Cubo\Routing\Route;
use Cubo\Tests\Support\Controllers\FakeController;
use Cubo\Tests\Support\Views\FakeView;
use Cubo\Tests\Support\Views\SpyHelperView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

#[CoversClass(Controller::class)]
final class ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        SpyHelperView::reset();
        $this->setDefaultViewFactory(null);
    }

    protected function tearDown(): void
    {
        $this->setDefaultViewFactory(null);
    }

    /**
     * A fabrica e estado estatico: zera entre os testes para nao vazar.
     */
    private function setDefaultViewFactory(?\Closure $factory): void
    {
        $prop = new ReflectionProperty(Controller::class, '_defaultViewFactory');
        $prop->setValue(null, $factory);
    }

    public function testConstrutorHonraAViewInjetada(): void
    {
        // O BUG do v1: o construtor RECEBIA $view e ignorava, cravando
        // DefaultView::getInstance() -- o framework dependendo da app.
        $view = new FakeView();

        $controller = new FakeController(null, new Request(), $view);

        $this->assertSame($view, $controller->getView());
    }

    public function testSemViewInjetadaUsaAFabricaPadraoDoBootstrap(): void
    {
        $padrao = new FakeView();
        Controller::setDefaultViewFactory(fn() => $padrao);

        // e assim que o kernel instancia: new $control($route)
        $controller = new FakeController(new Route('index', 'index'));

        $this->assertSame($padrao, $controller->getView());
    }

    public function testSemViewESemFabricaRegistradaLanca(): void
    {
        $this->expectException(RuntimeException::class);

        new FakeController();
    }

    public function testDisplayRenderizaAView(): void
    {
        $view = new SpyHelperView();
        $controller = new FakeController(null, new Request(), $view);

        $controller->display();

        $this->assertSame(1, SpyHelperView::$renders);
    }

    public function testSetViewTrocaAView(): void
    {
        $controller = new FakeController(null, new Request(), new FakeView());
        $outra = new FakeView();

        $controller->setView($outra);

        $this->assertSame($outra, $controller->getView());
    }

    public function testGetModuleDevolveOProprioControllerQuandoNaoHaModulo(): void
    {
        $controller = new FakeController(null, new Request(), new FakeView());

        $this->assertSame($controller, $controller->getModule());
    }

    public function testGetModuleDevolveOModuloDespachado(): void
    {
        // E assim que o framework despacha: o CoreController (main controller)
        // resolve quem responde pela url e o orquestrador renderiza
        // $core->getModule()->display() -- a view do MODULO, nao a do core.
        $core = new FakeController(null, new Request(), new FakeView());
        $modulo = new FakeController(null, new Request(), new FakeView());

        $core->setModule($modulo);

        $this->assertSame($modulo, $core->getModule());
    }

    public function testRota(): void
    {
        // REFAC 8: era um array cru ['controller' => ..., 'method' => ...];
        // agora e o VO Route que o Router ja devolvia desde o REFAC 5.
        $rota = new Route('financeiro', 'gridMenus', ['id' => '7']);

        $controller = new FakeController($rota, new Request(), new FakeView());

        $this->assertSame($rota, $controller->getRoute());
        $this->assertSame('financeiro', $controller->getRoute()->controller);

        $controller->setRoute(null);
        $this->assertNull($controller->getRoute());
    }
}
