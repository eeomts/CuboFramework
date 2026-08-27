<?php

namespace Cubo\Tests\Unit;

use Cubo\Config;
use Cubo\Cubo;
use Cubo\Exceptions\CuboException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
#[CoversClass(Cubo::class)]
final class BootstrapTest extends TestCase
{
    private const APP = __DIR__ . '/../Support/app';

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBootstrapCarregaOIniDaRaizInformada(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/';

        (new Cubo(self::APP))->bootstrap();

        $config = Config::getInstance();
        $this->assertSame('http://example.com/blog/', $config->getConfig('ini.cubo.host'));
        $this->assertSame('blog_', $config->getConfig('ini.cubo.database_prefix'));
        $this->assertSame('blog', $config->getConfig('ini.database.db'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBootstrapDefineCuboRaizComoARaizDaApp(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/';

        (new Cubo(self::APP))->bootstrap();

        $this->assertSame(
            realpath(self::APP) . DIRECTORY_SEPARATOR,
            realpath(CUBO_RAIZ) . DIRECTORY_SEPARATOR
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBootstrapDefineCuboDirNameSemOProtocolo(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/';

        (new Cubo(self::APP))->bootstrap();

        $this->assertSame('example.com/blog/', CUBO_DIR_NAME);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCuboRootApontaParaOSrcDoFramework(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/';

        (new Cubo(self::APP))->bootstrap();

        $this->assertFileExists(CUBO_ROOT . 'Cubo.php');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBootstrapFalhaComMensagemQuandoNaoHaIni(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/';

        $this->expectException(CuboException::class);
        $this->expectExceptionMessageMatches('/config\.ini nao encontrado/');

        (new Cubo(__DIR__ . '/../Support/app-que-nao-existe'))->bootstrap();
    }
}
