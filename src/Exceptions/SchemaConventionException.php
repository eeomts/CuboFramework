<?php

namespace Cubo\Exceptions;

/**
 * Lançada quando uma migration desrespeita a convenção de schema do Cubo.
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class SchemaConventionException extends CuboException
{
    /** @param list<string> $violacoes */
    public static function for(string $tabela, array $violacoes): self
    {
        $lista = implode(PHP_EOL . '  - ', $violacoes);

        return new self(
            "A tabela '{$tabela}' foge da convenção de schema do Cubo seu burro:"
            . PHP_EOL . '  - ' . $lista,
            self::CODE_SCHEMA_CONVENTION,
        );
    }
}
