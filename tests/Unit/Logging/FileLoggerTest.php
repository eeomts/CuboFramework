<?php

namespace Cubo\Tests\Unit\Logging;

use Cubo\Logging\FileLogger;
use Cubo\Logging\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FileLogger::class)]
final class FileLoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cubo_filelogger_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->logFile)) {
             unlink($this->logFile);
        }
    }

    public function testCumpreOContratoDeLogger(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, new FileLogger($this->logFile));
    }

    public function testEscreveMensagemComNivelETimestamp(): void
    {
        (new FileLogger($this->logFile))->error('bosta algo quebrou');

        $conteudo = file_get_contents($this->logFile);

        $this->assertStringContainsString('[ERROR]', $conteudo);
        $this->assertStringContainsString('bosta algo quebrou', $conteudo);
        
        $this->assertMatchesRegularExpression(
            '/^\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}\] \[ERROR\] bosta algo quebrou/',
            $conteudo,
        );
    }

    public function testAcrescentaSemSobrescrever(): void
    {
        $logger = new FileLogger($this->logFile);
        $logger->error('primeira');
        $logger->error('segunda');

        $linhas = array_filter(explode(PHP_EOL, trim(file_get_contents($this->logFile))));

        $this->assertCount(2, $linhas);
        $this->assertStringContainsString('primeira', $linhas[0]);
        $this->assertStringContainsString('segunda', $linhas[1]);
    }

    public function testCaiNoErrorLogQuandoOArquivoNaoPodeSerEscrito(): void
    {
        
        $errorLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cubo_errorlog_' . uniqid() . '.log';
        $original = ini_set('error_log', $errorLog);

        try {
            
            $logger = new FileLogger(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nao-existe' . DIRECTORY_SEPARATOR . 'x.log');
            $logger->error('mensagem');
        } finally {
            ini_set('error_log', $original);
        }

        $this->assertStringContainsString('não foi possível escrever', file_get_contents($errorLog));
        @unlink($errorLog);
    }
}
