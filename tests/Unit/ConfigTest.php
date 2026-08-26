<?php

namespace Cubo\Tests\Unit;

use Cubo\Config;
use RuntimeException;
use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        // Instancia sem o construtor/singleton para isolar cada teste; aqui
        // testamos so a logica de dados (setConfig/getConfig/appFolder), sem
        // tocar em parse_ini_file() nem em constantes globais.
        $this->config = (new ReflectionClass(Config::class))->newInstanceWithoutConstructor();
    }

    public function testGetAppFolderRootLancaQuandoNaoDefinido(): void
    {
        $this->expectException(RuntimeException::class);

        $this->config->getAppFolderRoot();
    }

    public function testSetAppFolderDefineORoot(): void
    {
        $this->config->setAppFolder('Cms');

        $this->assertSame('Cms', $this->config->getAppFolderRoot());
    }

    public function testGetConfigLeValorAninhadoPorPonto(): void
    {
        // Cobre o acesso por ponto que aposentou o eval do v1.
        $this->config->setConfig('ini', ['cubo' => ['host' => 'https://x.com']]);

        $this->assertSame('https://x.com', $this->config->getConfig('ini.cubo.host'));
    }

    public function testGetConfigRetornaNullQuandoCaminhoNaoExiste(): void
    {
        $this->config->setConfig('ini', ['cubo' => ['host' => 'https://x.com']]);

        $this->assertNull($this->config->getConfig('ini.cubo.inexistente'));
        $this->assertNull($this->config->getConfig('nada'));
    }
}
