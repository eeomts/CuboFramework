<?php

namespace Cubo\Tests\Unit;

use Cubo\ErrorHandler;
use Cubo\Exceptions\ControllerNotFoundException;
use Cubo\Exceptions\CuboException;
use Cubo\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ErrorHandler::class)]
final class ErrorHandlerTest extends TestCase
{
    private InMemoryLogger $logger;
    private ErrorHandler $handler;

    protected function setUp(): void
    {
        $this->logger = new InMemoryLogger();
        $this->handler = new ErrorHandler($this->logger, 'http://localhost');
    }

    public function testFormatDescreveCodigoMensagemEArquivo(): void
    {
        $e = ControllerNotFoundException::for('FoobarController');

        $texto = $this->handler->format($e);

        $this->assertStringContainsString('[107]', $texto);
        $this->assertStringContainsString('FoobarController', $texto);
        $this->assertStringContainsString(__FILE__, $texto); // aponta onde foi lançada
    }

    public function testFormatPercorreACadeiaDeCausas(): void
    {
        $raiz = new \RuntimeException('falha de IO na raiz');
        $e = new ControllerNotFoundException('topo', CuboException::CODE_CONTROLLER_MISSING, $raiz);

        $texto = $this->handler->format($e);

        $this->assertStringContainsString('topo', $texto);
        $this->assertStringContainsString('Causado por: falha de IO na raiz', $texto);
    }

    public function testFormatNaoCarimbaTimestamp(): void
    {
        // o envelope de data/nível é responsabilidade do logger, não do handler
        $texto = $this->handler->format(new \RuntimeException('x'));

        $this->assertDoesNotMatchRegularExpression('/\d{2}\/\d{2}\/\d{4}/', $texto);
    }

    public function testFormatEPuroRetornaMesmaSaida(): void
    {
        $e = new \RuntimeException('idempotente');

        $this->assertSame($this->handler->format($e), $this->handler->format($e));
    }

    public function testLogRepassaAExcecaoFormatadaAoLogger(): void
    {
        $e = ControllerNotFoundException::for('X');

        $this->handler->log($e);

        $this->assertCount(1, $this->logger->errors);
        $this->assertSame($this->handler->format($e), $this->logger->errors[0]);
    }

    public function testQualquerLoggerInterfaceEncaixaNoHandler(): void
    {
        // troca de implementação sem tocar no ErrorHandler (inversão de dependência)
        $outroLogger = new class implements \Cubo\Logging\LoggerInterface {
            public ?string $capturado = null;
            public function error(string $message): void { $this->capturado = $message; }
        };

        (new ErrorHandler($outroLogger, 'https://x/'))->log(new \LogicException('via classe anonima'));

        $this->assertStringContainsString('via classe anonima', $outroLogger->capturado);
    }

    /**
     * register() nao tinha teste nenhum, e por isso pode sumir sem que a suite
     * reclame. Aqui checamos que ele instala mesmo o handler global.
     */
    public function testRegisterInstalaOTratadorGlobalDeExcecoes(): void
    {
        $this->handler->register();

        // set_exception_handler devolve o handler que estava no lugar
        $instalado = set_exception_handler(null);

        restore_exception_handler(); // desfaz o set_exception_handler(null)
        restore_exception_handler(); // desfaz o register()

        $this->assertInstanceOf(\Closure::class, $instalado);
    }
}
