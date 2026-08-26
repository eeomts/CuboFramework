<?php

namespace Cubo\Tests\Support;

use Cubo\Logging\LoggerInterface;

/**
 * Logger de teste: guarda as mensagens em memória em vez de gravar em
 * arquivo. Permite inspecionar o que o ErrorHandler mandou logar sem tocar em
 * disco — e comprova que qualquer LoggerInterface encaixa no handler.
 */
final class InMemoryLogger implements LoggerInterface
{
    /** @var list<string> */
    public array $errors = [];

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }
}





