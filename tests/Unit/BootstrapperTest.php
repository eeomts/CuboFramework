<?php

namespace Cubo\Tests\Unit;

use Cubo\Bootstrapper;
use Cubo\Config;
use Cubo\Controller;
use Cubo\Cubo;
use Cubo\Exceptions\CuboException;
use Cubo\Http\Request;
use Cubo\Routing\Route;
use Cubo\Tests\Support\Controllers\SpyController;
use Cubo\Tests\Support\Views\RecordingView;
use Cubo\View\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Bootstrapper::class)]
final class BootstrapperTest extends TestCase
{
    private const APP = __DIR__ . '/../Support/app';

    private function bootFixture(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/';

        (new Cubo(self::APP))->bootstrap();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTemplatesDoIniViramListaDeCaminhosAbsolutos(): void
    {
        $this->bootFixture();

        $roots = Config::getInstance()->getConfig(View::TEMPLATE_ROOTS);

        $this->assertIsArray($roots);
        $this->assertCount(1, $roots);
        $this->assertFileExists($roots[0] . 'hello.php');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testViewDoIniViraAFactoryPadraoDoController(): void
    {
        $this->bootFixture();

        $this->assertInstanceOf(RecordingView::class, (new SpyController())->getView());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testControllersDoIniResolvemORotaSemPassarNamespace(): void
    {
        $this->bootFixture();

        $controller = (new Cubo(self::APP))->dispatch(new Route('spy', 'index'), new Request());

        $this->assertInstanceOf(SpyController::class, $controller);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTimezoneDoIniEhAplicado(): void
    {
        $this->bootFixture();

        $this->assertSame('America/Sao_Paulo', date_default_timezone_get());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testViewInexistenteNoIniFalhaComMensagemClara(): void
    {
        $config = (new ReflectionClass(Config::class))->newInstanceWithoutConstructor();
        $config->setConfig('ini', ['app' => ['view' => 'App\\Nao\\Existe']]);

        $this->expectException(CuboException::class);
        $this->expectExceptionMessageMatches('/nao existe ou nao estende/');

        (new Bootstrapper($config, self::APP))->boot();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSemSecaoAppNadaEhAplicado(): void
    {
        $config = (new ReflectionClass(Config::class))->newInstanceWithoutConstructor();
        $config->setConfig('ini', ['cubo' => ['envi' => 'development']]);

        (new Bootstrapper($config, self::APP))->boot();

        $this->assertNull($config->getConfig(View::TEMPLATE_ROOTS));
    }
}
