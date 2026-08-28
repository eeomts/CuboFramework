<?php

namespace Cubo\Tests\Unit\Console;

use Cubo\Console\CommandRegistry;
use Cubo\Console\Input;
use Cubo\Console\Kernel;
use Cubo\Console\Output;
use Cubo\Cubo;
use Cubo\Tests\Support\Console\ExplodingCommand;
use Cubo\Tests\Support\Console\SpyCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Kernel::class)]
#[CoversClass(Output::class)]
final class KernelTest extends TestCase
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    private Output $output;

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'r+');
        $this->stderr = fopen('php://memory', 'r+');
        $this->output = new Output($this->stdout, $this->stderr);
    }

    private function readStdout(): string
    {
        rewind($this->stdout);

        return (string) stream_get_contents($this->stdout);
    }

    private function readStderr(): string
    {
        rewind($this->stderr);

        return (string) stream_get_contents($this->stderr);
    }

    public function testExecutaOComandoEDevolveOCodigoDele(): void
    {
        $kernel = new Kernel(new CommandRegistry([SpyCommand::class]));

        $code = $kernel->run(Input::fromArgv(['cubo.php', 'spy', 'a', 'b']), $this->output);

        $this->assertSame(Kernel::EXIT_SUCCESS, $code);
        $this->assertStringContainsString('spy: a,b', $this->readStdout());
    }

    public function testSemArgumentoNenhumMostraOHelp(): void
    {
        $code = (new Kernel(CommandRegistry::default()))
            ->run(Input::fromArgv(['cubo.php']), $this->output);

        $this->assertSame(Kernel::EXIT_SUCCESS, $code);
        $this->assertStringContainsString('Uso: cubo', $this->readStdout());
    }

    public function testVersionFlagCaiNoComandoVersion(): void
    {
        $code = (new Kernel(CommandRegistry::default()))
            ->run(Input::fromArgv(['cubo.php', '--version']), $this->output);

        $this->assertSame(Kernel::EXIT_SUCCESS, $code);
        $this->assertStringContainsString(Cubo::version(), $this->readStdout());
    }

    public function testComandoDesconhecidoFalhaEEscreveNoStderr(): void
    {
        $kernel = new Kernel(CommandRegistry::default());

        $code = $kernel->run(Input::fromArgv(['cubo.php', 'inventado']), $this->output);

        $this->assertSame(Kernel::EXIT_FAILURE, $code);
        $this->assertStringContainsString('Comando nao encontrado: inventado', $this->readStderr());
        $this->assertSame('', $this->readStdout());
    }

    public function testCuboExceptionViraMensagemDeErroENaoStackTrace(): void
    {
        $kernel = new Kernel(new CommandRegistry([ExplodingCommand::class]));

        $code = $kernel->run(Input::fromArgv(['cubo.php', 'explode']), $this->output);

        $this->assertSame(Kernel::EXIT_FAILURE, $code);
        $this->assertStringContainsString('estourou de proposito', $this->readStderr());
    }
}
