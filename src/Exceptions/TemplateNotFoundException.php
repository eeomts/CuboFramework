<?php

namespace Cubo\Exceptions;

/**
 * Lancada quando a View aponta para um template que nao existe em nenhuma
 * das raizes configuradas (template_root, core_template_root, custom_template_root).
 *
 * No v1 esse caso era SILENCIOSO: o render() testava as tres raizes e, se
 * nenhuma casasse, simplesmente nao incluia nada -- a pagina saia em branco,
 * sem erro nenhum (apesar do docblock do metodo prometer @throws).
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class TemplateNotFoundException extends CuboException
{
    /**
     * @param list<string> $roots raizes onde o template foi procurado
     */
    public static function for(string $template, array $roots = []): self
    {
        $onde = $roots === []
            ? 'nenhuma raiz de template esta configurada'
            : 'procurado em: ' . implode(', ', $roots);

        return new self(
            "Template nao encontrado: {$template} ({$onde})",
            self::CODE_TEMPLATE_MISSING,
        );
    }
}
