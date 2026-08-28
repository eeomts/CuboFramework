<?php

namespace Cubo\Tests\Unit\Routing;

use Cubo\Bootstrapper;
use Cubo\Config;
use Cubo\Exceptions\CuboException;
use Cubo\Http\Request;
use Cubo\Routing\RouteCollection;
use Cubo\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Bootstrapper::class)]
final class RotasPeloIniTest extends TestCase
{
    private const APP = __DIR__ . '/../../Support/app';

    private function configCom(array $app): Config
    {
        $config = (new ReflectionClass(Config::class))->newInstanceWithoutConstructor();
        $config->setConfig('ini', ['app' => $app]);

        return $config;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTabelaDeclaradaNoIniChegaAoRouter(): void
    {
        $config = $this->configCom(['routes' => 'routing/routes.php']);

        (new Bootstrapper($config, self::APP))->boot();

        $this->assertInstanceOf(RouteCollection::class, $config->getConfig(Router::ROUTES));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSemAChaveRoutesNadaEhCarregado(): void
    {
        $config = $this->configCom([]);

        (new Bootstrapper($config, self::APP))->boot();

        $this->assertNull($config->getConfig(Router::ROUTES));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testArquivoInexistenteFalhaComMensagemClara(): void
    {
        $config = $this->configCom(['routes' => 'config/nao-existe.php']);

        $this->expectException(CuboException::class);
        $this->expectExceptionMessageMatches('/nao existe/');

        (new Bootstrapper($config, self::APP))->boot();
    }

    /** O Router pega a tabela do Config quando nao recebe uma injetada. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRouterUsaATabelaCarregadaPeloBootstrapper(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/loja/9';

        (new \Cubo\Cubo(self::APP))->bootstrap();

        $rota = (new Router())->parseUrl(new Request(server: [
            'REQUEST_URI' => '/blog/loja/9',
            'REQUEST_METHOD' => 'GET',
        ]));

        $this->assertTrue($rota->ehDeclarada());
        $this->assertSame('editar', $rota->method);
        $this->assertSame(['id' => '9'], $rota->params);
    }
}
