<?php

namespace Cubo;

/**
 * Utils de segurança do framework.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Security)
 * @author v2: Mateus - github.com/eeomts
 */
final class Security
{
    /** Escapa um valor para inserção segura em HTML (defesa contra XSS). */
    public static function escape(string $val): string
    {
        // ENT_SUBSTITUTE: byte UTF-8 invalido vira caractere de reposicao, nao string vazia
        return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function randomPassword(int $tam = 10): string
    {
        $conjunto = 'ABCDEFGHIJLMNOPQRSTUVXZYWKabcdefghijlmnopqrstuvxzywk0123456789';
        $max = strlen($conjunto) - 1;
        $password = '';

        for ($i = 0; $i < $tam; $i++) {
            $password .= $conjunto[random_int(0, $max)];
        }

        return $password;
    }
}
