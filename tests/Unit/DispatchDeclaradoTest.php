<?php

namespace Cubo\Tests\Unit;

use Cubo\Cubo;
use Cubo\Exceptions\ActionNotFoundException;
use Cubo\Http\Request;
use Cubo\Routing\Route;
use Cubo\Tests\Support\Controllers\LojaController;
use Cubo\Tests\Support\Views\RecordingView;
use Cubo\Controller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * As duas formas de despachar: rota declarada chama a action declarada, rota por
 * convencao continua caindo em index().
 */
#[CoversClass(Cubo::class)]
final class DispatchDeclaradoTest extends TestCase
{
    private const APP_ROOT = __DIR__ . '/../Support/app';

    protected function setUp(): void
    {
        Controller::setDefaultViewFactory(fn () => new RecordingView());
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Controller::class, '_defaultViewFactory'))->setValue(null, null);
    }

    private function rotaDeclarada(string $action, array $params = []): Route
    {
        return new Route(
            controller: LojaController::class,
            method: $action,
            params: $params,
            controllerClass: LojaController::class,
        );
    }

    public function testRotaDeclaradaChamaAActionDeclarada(): void
    {
        $controller = (new Cubo(self::APP_ROOT))
            ->dispatch($this->rotaDeclarada('listar'), new Request(server: []));

        $this->assertSame(['initialize', 'listar'], $controller->calls);
    }

    public function testRotaDeclaradaNaoChamaIndex(): void
    {
        $controller = (new Cubo(self::APP_ROOT))
            ->dispatch($this->rotaDeclarada('salvar'), new Request(server: []));

        $this->assertNotContains('index', $controller->calls);
    }

    public function testAActionEnxergaOsParametrosDaRota(): void
    {
        $controller = (new Cubo(self::APP_ROOT))
            ->dispatch($this->rotaDeclarada('editar', ['id' => '7']), new Request(server: []));

        $this->assertSame(['initialize', 'editar:7'], $controller->calls);
    }

    public function testRotaPorConvencaoContinuaCaindoEmIndex(): void
    {
        $controller = (new Cubo(self::APP_ROOT, LojaController::class))
            ->dispatch(new Route('loja', 'listar'), new Request(server: []));

        $this->assertSame(['initialize', 'index'], $controller->calls);
    }

    public function testActionInexistenteEstoura(): void
    {
        $this->expectException(ActionNotFoundException::class);

        (new Cubo(self::APP_ROOT))
            ->dispatch($this->rotaDeclarada('naoExiste'), new Request(server: []));
    }

    /** Rota apontando para metodo do proprio Cubo\Controller seria execucao arbitraria. */
    public function testActionHerdadaDoControllerBaseEhRecusada(): void
    {
        $this->expectException(ActionNotFoundException::class);
        $this->expectExceptionMessageMatches('/metodo publico declarado no proprio controlador|método público declarado no próprio controlador/');

        (new Cubo(self::APP_ROOT))
            ->dispatch($this->rotaDeclarada('setView'), new Request(server: []));
    }

    public function testActionNaoPublicaEhRecusada(): void
    {
        $this->expectException(ActionNotFoundException::class);

        (new Cubo(self::APP_ROOT))
            ->dispatch($this->rotaDeclarada('interna'), new Request(server: []));
    }
}
