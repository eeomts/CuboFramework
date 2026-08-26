<?php

namespace Cubo;

/**
 * Utils de segurança do framework.
 *
 * Não existe "limpeza de entrada" genérica aqui: a defesa mora na camada certa
 * -- SQLi no bind do Eloquent, XSS no escape da SAÍDA, conforme o contexto. É
 * esse encoding de saída que o escape() centraliza para a View usar.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Security)
 * @author v2: Mateus - github.com/eeomts
 */
final class Security
{
    /**
     * Escapa um valor para inserção segura em HTML (defesa contra XSS).
     *
     * Converte caracteres especiais em entidades HTML. Usar SEMPRE ao imprimir
     * dado vindo do usuário dentro de uma página.
     *
     * @param string $val Texto cru (ex.: vindo de $_POST) a ser exibido.
     * @return string Texto seguro para colocar em contexto HTML.
     */
    public static function escape(string $val): string
    {
        // ENT_QUOTES -> escapa aspas simples E duplas (atributos HTML).
        // ENT_SUBSTITUTE -> bytes UTF-8 invalidos viram caractere de reposicao
        // em vez de string vazia.
        return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Gera uma senha aleatória alfanumérica. (ex-Cubo_Tools::geraPass)
     *
     * O v1 usava rand(), que NAO e criptograficamente seguro; aqui usamos
     * random_int(), apropriado para senhas/tokens.
     *
     * @param int $tam Quantidade de caracteres (padrao 10).
     */
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

/*
 * GUIA DE MIGRACAO - Cubo_Security -> Cubo\Security
 *
 * NOVOS
 *   escape(string): string -- htmlspecialchars para saida em HTML
 *
 * MOVIDOS PARA CA
 *   Cubo_Tools::geraPass -> randomPassword, agora sobre random_int
 *
 * DESCARTADOS
 *   activeSecurity / filterValue X o WAF de blacklist saiu por inteiro; SQLi
 *     morre no bind do Eloquent e XSS no escape da saida. NAO REINTRODUZIR.
 */
