<?php

namespace Cubo;

/**
 * Gerencia a sessao do PHP (wrapper sobre $_SESSION).
 *
 * @package Cubo
 * @author v1 Joao (Cubo_Session)
 *
 * V2 - core cubo atualizado para php 8+; singleton no padrao do Config,
 * leitura aninhada delegada ao Arr::get (fim do eval do v1).
 * @author v2 Mateus - github.com/eeomts
 */

use Cubo\Tools\Arr;

class Session
{

    private static ?Session $_instance = null;

    private function __construct()
    {
        $this->start();
    }

    public static function getInstance(): static
    {
        if (static::$_instance === null) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    /**
     * Inicia a sessao se ainda nao houver uma ativa.
     *
     * v1 passava um $index para session_start(), que na verdade espera um array
     * de options -> parametro removido. O teste
     * @example get('login.nome') retorna $_SESSION['login']['nome']
     *
     * Substitui o getVar() do v1, que montava uma string e chamava eval(); agora
     * a travessia aninhada e feita pelo Arr::get.
     */
    public function get(string $index, mixed $default = ''): mixed
    {
        return Arr::get($_SESSION, $index, $default);
    }

    /**
     * Registra um valor na sessao por caminho com ponto.
     *
     * @example set('login.nome', 'Pedro') faz $_SESSION['login']['nome'] = 'Pedro'
     */
    public function set(string $index, mixed $value): void
    {
        $keys = explode('.', $index);
        $last = array_pop($keys);

        $ref = &$_SESSION;
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }

        $ref[$last] = $value;
    }

    /**
     * Retorna todos os dados da sessao.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $_SESSION;
    }

    /**
     * Remove um item da sessao por caminho com ponto, sem eval.
     *
     * @example remove('login.nome') faz unset em $_SESSION['login']['nome']
     */
    public function remove(string $index): void
    {
        $keys = explode('.', $index);
        $last = array_pop($keys);

        $ref = &$_SESSION;
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                return;
            }
            $ref = &$ref[$key];
        }

        unset($ref[$last]);
    }
}
