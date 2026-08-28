<?php

namespace Cubo;

use Cubo\Logging\LoggerInterface;

/**
 * Registra e encaminha exceções não tratadas.
 *
 * @package Cubo
 * @author v1: João (Cubo_ErrorManager::errorHandler)
 * @author v2: Mateus - github.com/eeomts
 */
final class ErrorHandler
{
    /**
     * @param LoggerInterface $logger destino do log (arquivo, stderr, Monolog...)
     * @param string $host host base para montar a URL da pagina de erro
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $host,
    ) {}

    /** Registra como tratador global de excecoes nao capturadas. */
    public function register(): void
    {
        set_exception_handler($this->handle(...));
    }

    /**
     * Trata a exceção: loga e redireciona para a página de erro. Encerra a
     * execução (equivalente ao exit do legado).
     */
    public function handle(\Throwable $e): void
    {
        $this->log($e);
        $this->redirect($e);
    }

    /**
     * Repassa a exceção formatada ao logger. O timestamp/nível é o logger quem
     * carimba — aqui só descrevemos a exceção.
     */
    public function log(\Throwable $e): void
    {
        $this->logger->error($this->format($e));
    }

    /**
     * Descreve a exceção como texto. Método puro (sem I/O) → testável.
     * Percorre a cadeia de causas (getPrevious) que o v1 não registrava.
     */
    public function format(\Throwable $e): string
    {
        $lines = [];

        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            $prefix = ($cur === $e) ? '' : 'Causado por: ';
            $lines[] = sprintf(
                '[%d] %s%s em %s:%d',
                $cur->getCode(),
                $prefix,
                $cur->getMessage(),
                $cur->getFile(),
                $cur->getLine(),
            );
        }

        $lines[] = $e->getTraceAsString();

        return implode(PHP_EOL, $lines);
    }

    /**
     * Encaminha para error/index/code/{code} e encerra.
     */
    private function redirect(\Throwable $e): void
    {
        header("Location: {$this->host}error/index/code/{$e->getCode()}");
        exit;
    }
}
