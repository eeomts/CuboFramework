<?php

namespace Cubo\Logging;

/**
 * Logger que grava em arquivo (modo append, com lock).
 *
 * E esta classe que envelopa a mensagem com timestamp e nivel; quem chama so
 * passa o texto. Escrita que falha cai no error_log do PHP.
 *
 * @package Cubo
 * @author v1: João (Cubo_ErrorManager::errorHandler)
 * @author v2: Mateus - github.com/eeomts
 */
final class FileLogger implements LoggerInterface
{
    /** @param string $logFile caminho absoluto do arquivo de log */
    public function __construct(private readonly string $logFile) {}

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $line = sprintf('[%s] [%s] %s%s', date('d/m/Y H:i:s'), $level, $message, PHP_EOL);

        if (@file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('Cubo\Logging\FileLogger: não foi possível escrever em ' . $this->logFile);
            error_log($line);
        }
    }
}
