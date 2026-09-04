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
     * @param LoggerInterface $logger destino do log
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

    /** Loga, redireciona para a pagina de erro e ENCERRA a execucao. */
    public function handle(\Throwable $e): void
    {
        $this->log($e);
        $this->redirect($e);
    }

    /** O timestamp e o nivel quem carimba e o logger. */
    public function log(\Throwable $e): void
    {
        $this->logger->error($this->format($e));
    }

    /** Metodo puro, sem I/O. Percorre a cadeia de causas (getPrevious). */
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

    /** Encaminha para error/index/code/{code} e encerra. */
    private function redirect(\Throwable $e): void
    {
        header("Location: {$this->host}error/index/code/{$e->getCode()}");
        exit;
    }
}
