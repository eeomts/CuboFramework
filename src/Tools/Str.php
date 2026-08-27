<?php

namespace Cubo\Tools;

/**
 * Utilitários de manipulação de strings.
 *
 * @package Cubo
 * @author v1: Cristiano / Reginaldo (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Str
{
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
        'À' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C',
    ];

    private const SPECIAL_CHARS = [
        '.', '!', '@', '#', '$', '%', '&', '*', '_', '-', '+', ' ',
        '|', '(', ')', '¨', '=', '/', '\\', "'",
    ];

    # ------------------------------------------------------------------ ACENTOS

    /** Remove acentos preservando o caixa. */
    public static function removeAccents(string $string): string
    {
        return strtr($string, self::ACCENTS);
    }

    /**
     * Slug para URL: sem acento, minúsculo, não-alfanuméricos viram o separador.
     *
     * @example slug('Coração à Vista!') retorna 'coracao-a-vista'
     */
    public static function slug(string $string, string $separator = '-'): string
    {
        $string = self::removeAccents($string);
        $string = mb_strtolower($string);
        $string = preg_replace('/[^a-z0-9]+/', $separator, $string);

        return trim($string, $separator);
    }

    /**
     * Versão "snake": sem acento, minúsculo, espaços viram underline.
     *
     * @example snake('São Paulo') retorna 'sao_paulo'
     */
    public static function snake(string $string): string
    {
        $string = self::removeAccents($string);
        $string = mb_strtolower($string);

        return str_replace(' ', '_', $string);
    }

    # ------------------------------------------------------------------ LIMPEZA

    /** Remove barras invertidas e stripslashes, aparando espaços. */
    public static function removeSlashes(string $string): string
    {
        return stripslashes(trim(str_replace('\\', '', $string)));
    }

    /** Mantém apenas letras e números. */
    public static function onlyAlphanumeric(string $string): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $string);
    }

    /** Troca aspas simples e duplas pela flag. */
    public static function removeQuotes(string $string, string $flag = '_'): string
    {
        return str_replace(['"', "'"], $flag, $string);
    }

    /** Troca caracteres especiais pela flag. */
    public static function cleanSpecialChars(string $string, string $flag = '_'): string
    {
        return str_replace(self::SPECIAL_CHARS, $flag, $string);
    }

    # ------------------------------------------------------------------ FORMATO

    /** Garante que o primeiro caractere seja letra; se for outro, prefixa 'a'. */
    public static function firstChar(string $string): string
    {
        return ctype_alpha(substr($string, 0, 1)) ? $string : 'a' . substr($string, 1);
    }

    /** $tipo === 1 vira MAIUSCULA, caso contrário minúscula. */
    public static function changeCase(string $string, int $tipo = 1): string
    {
        return $tipo === 1 ? mb_strtoupper($string) : mb_strtolower($string);
    }

    /**
     * Corta a string se ultrapassar o limite, anexando um sufixo.
     *
     * @example truncate('texto longo demais', 5) retorna 'texto ...'
     */
    public static function truncate(string $string, int $length = 35, string $end = '...'): string
    {
        return mb_strlen($string) > $length
            ? mb_substr($string, 0, $length) . ' ' . $end
            : $string;
    }

    /**
     * Aplica uma máscara: cada '#' consome um caractere de $value; o resto é literal.
     *
     * @example mask('11987654321', '(##) #####-####') retorna '(11) 98765-4321'
     */
    public static function mask(string $value, string $mask): string
    {
        $result = '';
        $k = 0;

        for ($i = 0, $len = strlen($mask); $i < $len; $i++) {
            if ($mask[$i] === '#') {
                if (isset($value[$k])) {
                    $result .= $value[$k++];
                }
            } else {
                $result .= $mask[$i];
            }
        }

        return $result;
    }

    /**
     * Converte um nome de coluna do banco em texto legível, removendo prefixos.
     *
     * @example columnToWord('fk_cidade') retorna 'Cidade'
     *
     * @param list<string> $hideKey prefixos/segmentos a ocultar
     */
    public static function columnToWord(string $string, array $hideKey = [], string $slug = '_'): string
    {
        if (empty($hideKey)) {
            $hideKey = ['fk', 'data', 'tel', 'num', 'cep', 'mon', 'pass', 'perc'];
        }

        $parts = explode($slug, $string);
        $prefix = current($parts);

        foreach ($parts as $key => &$value) {
            if (in_array($value, $hideKey, true)) {
                unset($parts[$key]);
            }
            $value = ucfirst($value);
        }
        unset($value);

        $string = implode(' ', $parts);

        if (!in_array($prefix, $hideKey, true)) {
            return ucfirst($string);
        }

        return ucfirst(str_replace(ucfirst($prefix) . ' ', '', $string));
    }

    # ------------------------------------------------------------------- ENCODE

    /** NAO e criptografia: base64 por segmento e no todo, ofuscacao reversivel. */
    public static function cuboEncode(string $string): string
    {
        $flag = '_';
        $string = self::cleanSpecialChars($string, $flag);
        $parts = array_map('base64_encode', explode($flag, $string));

        return base64_encode(implode($flag, $parts));
    }

    /** Inverte cuboEncode(). */
    public static function cuboDecode(string $string): string
    {
        $flag = '_';
        $string = base64_decode($string);
        $parts = array_map('base64_decode', explode($flag, $string));

        return implode($flag, $parts);
    }
}
