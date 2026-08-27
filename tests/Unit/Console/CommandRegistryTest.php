<?php

namespace Cubo\Tests\Unit\Console;

use Cubo\Console\CommandRegistry;
use Cubo\Console\Commands\HelpCommand;
use Cubo\Console\Commands\VersionCommand;
use Cubo\Exceptions\CommandNotFoundException;
use Cubo\Tests\Support\Console\SpyCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandRegistry::class)]
final class CommandRegistryTest extends TestCase
{
    public function testRegistraPeloNomeDeclaradoNaClasse(): void
    {
        $registry = new CommandRegistry([SpyCommand::class]);

        $this->assertTrue($registry->has('spy'));
        $this->assertInstanceOf(SpyCommand::class, $registry->get('spy'));
    }

    public function testGetLancaQuandoOComandoNaoExiste(): void
    {
        $registry = new CommandRegistry();

        $this->expectException(CommandNotFoundException::class);

        $registry->get('inexistente');
    }

    public function testDefaultTrazOsComandosDoFramework(): void
    {
        $registry = CommandRegistry::default();

        $this->assertTrue($registry->has('help'));
        $this->assertTrue($registry->has('version'));
        $this->assertInstanceOf(VersionCommand::class, $registry->get('version'));
        $this->assertInstanceOf(HelpCommand::class, $registry->get('help'));
    }

    public function testDescriptionsSaiEmOrdemAlfabetica(): void
    {
        $registry = new CommandRegistry([VersionCommand::class, SpyCommand::class]);

        $this->assertSame(['spy', 'version'], array_keys($registry->descriptions()));
        $this->assertSame('Comando de teste', $registry->descriptions()['spy']);
    }
}
