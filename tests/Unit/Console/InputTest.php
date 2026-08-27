<?php

namespace Cubo\Tests\Unit\Console;

use Cubo\Console\Input;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Input::class)]
final class InputTest extends TestCase
{
    public function testSeparaComandoArgumentosEOpcoes(): void
    {
        $input = Input::fromArgv(['cubo.php', 'init', 'blog', '--force']);

        $this->assertSame('init', $input->command);
        $this->assertSame(['blog'], $input->arguments);
        $this->assertSame(['force' => true], $input->options);
    }

    public function testOpcaoComValor(): void
    {
        $input = Input::fromArgv(['cubo.php', 'init', '--path=D:/sites/blog']);

        $this->assertSame('D:/sites/blog', $input->option('path'));
    }

    public function testOpcaoAntesDoComandoNaoViraComando(): void
    {
        $input = Input::fromArgv(['cubo.php', '--version']);

        $this->assertSame('', $input->command);
        $this->assertTrue($input->hasOption('version'));
    }

    public function testFlagCurtaEhTratadaComoOpcao(): void
    {
        $input = Input::fromArgv(['cubo.php', '-v']);

        $this->assertSame('', $input->command);
        $this->assertTrue($input->hasOption('v'));
    }

    public function testHasOptionAceitaVariosNomes(): void
    {
        $input = Input::fromArgv(['cubo.php', '-h']);

        $this->assertTrue($input->hasOption('help', 'h'));
        $this->assertFalse($input->hasOption('version', 'v'));
    }

    public function testPontoEhArgumentoValido(): void
    {
        $input = Input::fromArgv(['cubo.php', 'init', '.']);

        $this->assertSame('.', $input->argument(0));
    }

    public function testArgumentDevolveOPadraoQuandoNaoInformado(): void
    {
        $input = Input::fromArgv(['cubo.php', 'init']);

        $this->assertSame('.', $input->argument(0, '.'));
        $this->assertNull($input->argument(0));
    }

    public function testSemNenhumTokenTudoFicaVazio(): void
    {
        $input = Input::fromArgv(['cubo.php']);

        $this->assertSame('', $input->command);
        $this->assertSame([], $input->arguments);
        $this->assertSame([], $input->options);
    }
}
