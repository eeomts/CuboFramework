<?php

namespace Cubo\Tools;

/**
 * Utilitários de manipulação de arrays.
 *
 * Segundo slice da explosão da God Class Cubo_Tools. Operações de array puras
 * e estáticas. Alguns métodos do v1 foram corrigidos ou substituídos; veja o
 * GUIA DE MIGRACAO no fim do arquivo.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Arr
{
    /**
     * Busca um valor aninhado por caminho com ponto. Substitui o antigo
     * array_access(), que só montava a string "['a']['b']" (antipadrao ligado
     * a eval/variaveis-variaveis) em vez de retornar o valor.
     *
     * @example get(['a' => ['b' => 1]], 'a.b') retorna 1
     */
    public static function get(array $array, string $path, mixed $default = null): mixed
    {
        $value = $array;

        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Remove recursivamente as chaves numéricas, mantendo as associativas.
     * (ex-removeIndexArray)
     *
     * Serve para limpar o resultado de mysql_fetch_array(MYSQL_BOTH), que trazia
     * cada coluna duplicada em chave numérica E associativa. O v1 fazia isso via
     * unset posicional ($array[$i]), frágil; aqui usamos a própria chave.
     *
     * Nota: tende a ficar obsoleto após o REFAC 6 (Eloquent não gera chave dupla).
     */
    public static function stripNumericKeys(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = self::stripNumericKeys($value);
            } elseif (is_int($key)) {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Remove linhas duplicadas com base nos valores de um conjunto de colunas.
     * (ex-removeDuplicadosArray)
     *
     * Melhoria: usa a chave combinada como índice de um mapa (O(1) por linha),
     * em vez do in_array O(n) do v1.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    public static function dedupeBy(array $rows, array $columns): array
    {
        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $key = '';
            foreach ($columns as $column) {
                $key .= ($row[$column] ?? '') . '-';
            }

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * Junta as posições capitalizadas numa única string PascalCase.
     * (ex-captalizeArray, com o tipo corrigido)
     *
     * @example capitalizeJoin(['mateus', 'moreira']) retorna 'MateusMoreira'
     *
     * @param list<string> $array
     */
    public static function capitalizeJoin(array $array): string
    {
        return implode('', array_map('ucfirst', $array));
    }

    /**
     * Verifica se um valor existe em um array de qualquer profundidade.
     * (ex-in_array_r)
     *
     * Melhoria: genuinamente recursivo (o v1 só descia um nível). Mantém a
     * comparação fezes (==) do original.
     */
    public static function containsRecursive(mixed $needle, array $haystack): bool
    {
        foreach ($haystack as $item) {
            if (is_array($item)) {
                if (self::containsRecursive($needle, $item)) {
                    return true;
                }
            } elseif ($item == $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * funcao pika, pra nao ter que digitar o viado do <pre>
     * Imprime um valor formatado dentro de <pre> para depuração. (ex-printArray)
     *
     * O v1 dava exit (dump-and-die). Removido: dump apenas imprime.
     * Se quiser encerrar, chame: Arr::dump($x); exit;
     */
    public static function dump(mixed $value): void
    {
        echo '<pre>' . print_r($value, true) . '</pre>';
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Tools (array) -> Cubo\Tools\Arr
 *
 * RENOMEADOS
 *   removeDuplicadosArray -> dedupeBy
 *   captalizeArray -> capitalizeJoin
 *   in_array_r -> containsRecursive
 *   removeIndexArray -> stripNumericKeys
 *
 * MUDOU
 *   printArray -> dump -- sem exit; para encerrar use Arr::dump($a); exit;
 *
 * DESCARTADOS
 *   array_access X Arr::get($array, 'a.b')
 */
