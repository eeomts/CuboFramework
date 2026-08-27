<?php

namespace Cubo\Tools;

/**
 * Utilitários de manipulação de arrays.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Arr
{
    /**
     * Busca um valor aninhado por caminho com ponto.
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

    /** Remove recursivamente as chaves numéricas, mantendo as associativas. */
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
     * Remove linhas duplicadas pelos valores de um conjunto de colunas.
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
     * @param list<string> $array
     * @example capitalizeJoin(['mateus', 'moreira']) retorna 'MateusMoreira'
     */
    public static function capitalizeJoin(array $array): string
    {
        return implode('', array_map('ucfirst', $array));
    }

    /** Busca em qualquer profundidade, com comparacao frouxa (==). */
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

    /** Imprime dentro de <pre> para depuracao; nao encerra a execucao. */
    public static function dump(mixed $value): void
    {
        echo '<pre>' . print_r($value, true) . '</pre>';
    }
}
