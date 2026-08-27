<?php

namespace Cubo\Tests\Unit;

use Cubo\Controller;
use Cubo\Cubo;
use Cubo\Exceptions\ControllerNotFoundException;
use Cubo\Routing\Route;
use Cubo\Tests\Support\Controllers\CoreLikeController;
use Cubo\Tests\Support\Controllers\ImpostorController;
use Cubo\Tests\Support\Controllers\SpyController;
use Cubo\Tests\Support\Views\RecordingView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Cubo::class)]
final class CuboTest extends TestCase
{
    private const NS = 'Cubo\\Tests\\Support\\Controllers\\';
    private const APP_ROOT = __DIR__;

    protected function setUp(): void
    {
        // Uma View NOVA por chamada, para os testes distinguirem a view do core
        // da view do modulo.
        Controller::setDefaultViewFactory(fn() => new RecordingView());
    }

    protected function tearDown(): void
    {
        $prop = new ReflectionProperty(Controller::class, '_defaultViewFactory');
        $prop->setValue(null, null);
    }

    public function testDispatchRodaOCicloDoControlador(): void
    {
        $kernel = new Cubo(self::APP_ROOT, SpyController::class);

        $controller = $kernel->dispatch(new Route('spy', 'index'));

        $this->assertInstanceOf(SpyController::class, $controller);
        $this->assertSame(['initialize', 'index', 'display'], $controller->calls);
    }

    public function testDispatchEntregaARotaAoControlador(): void
    {
        $rota = new Route('spy', 'gridMenus', ['id' => '7']);
        $kernel = new Cubo(self::APP_ROOT, SpyController::class);

        $controller = $kernel->dispatch($rota);

        $this->assertSame($rota, $controller->getRoute());
    }

    public function testSemModuloRenderizaAPropriaView(): void
    {
        $kernel = new Cubo(self::APP_ROOT, SpyController::class);

        $controller = $kernel->dispatch(new Route('spy', 'index'));
        $view = $controller->getView();

        $this->assertInstanceOf(RecordingView::class, $view);
        $this->assertSame(1, $view->renders);
    }

    public function testComModuloResolvidoRenderizaAViewDoModulo(): void
    {
        // O v1 fazia $core->getModule()->display(): quem renderiza e o MODULO.
        $kernel = new Cubo(self::APP_ROOT, CoreLikeController::class);

        $core = $kernel->dispatch(new Route('spy', 'index'));
        $modulo = $core->getModule();

        $this->assertNotSame($core, $modulo);
        $this->assertSame(1, $modulo->getView()->renders);
        $this->assertSame(0, $core->getView()->renders, 'a view do core nao deve renderizar');
        $this->assertSame(['display'], $modulo->calls);
    }

    public function testSemMainControllerResolveOControladorPelaUrl(): void
    {
        $kernel = new Cubo(self::APP_ROOT, null, self::NS);

        $controller = $kernel->dispatch(new Route('spy', 'index'));

        $this->assertInstanceOf(SpyController::class, $controller);
    }

    public function testMainControllerTemPrecedenciaSobreAUrl(): void
    {
        $kernel = new Cubo(self::APP_ROOT, SpyController::class, self::NS);

        // a rota aponta para 'coreLike', mas o main controller manda
        $controller = $kernel->dispatch(new Route('coreLike', 'index'));

        $this->assertInstanceOf(SpyController::class, $controller);
    }

    public function testControladorInexistenteLanca(): void
    {
        $kernel = new Cubo(self::APP_ROOT, null, self::NS);

        $this->expectException(ControllerNotFoundException::class);

        $kernel->dispatch(new Route('naoExiste', 'index'));
    }

    public function testClasseQueNaoEUmControllerDoCuboLanca(): void
    {
        // Endurecimento: no v1 `new $control(...)` instanciava qualquer classe
        // terminada em "Controller" que a URL nomeasse.
        $this->assertTrue(class_exists(ImpostorController::class));

        $kernel = new Cubo(self::APP_ROOT, null, self::NS);

        $this->expectException(ControllerNotFoundException::class);

        $kernel->dispatch(new Route('impostor', 'index'));
    }

    public function testMainControllerQueNaoEUmControllerDoCuboLanca(): void
    {
        $kernel = new Cubo(self::APP_ROOT, ImpostorController::class);

        $this->expectException(ControllerNotFoundException::class);

        $kernel->dispatch(new Route('index', 'index'));
    }
}
