<?php

namespace Cubo\Tools;

/**
 * Utilitários numéricos: valor por extenso, formatação de moeda e de bytes.
 *
 * Terceiro slice da explosão da God Class Cubo_Tools. Consolida os dois métodos
 * "extenso" (que eram ~95% idênticos) num núcleo único e corrige bugs do v1;
 * veja o GUIA DE MIGRACAO no fim do arquivo.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Number
{
    /**
     * Valor monetário por extenso. (ex-extensoMoeda)
     *
     * @example spellCurrency(1234.56) retorna 'um mil, duzentos e trinta e quatro reais e cinquenta e seis centavos'
     */
    public static function spellCurrency(float $valor, bool $upper = false): string
    {
        
        return self::spell(
            $valor,
            ['centavo', 'real', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão'],
            ['centavos', 'reais', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões'],
            $upper,
        );
    }

    /**
     * Número por extenso (sem rótulo de moeda). (ex-extensoNumero)
     *
     * @example spellNumber(1234) retorna 'um mil e duzentos e trinta e quatro'
     */
    public static function spellNumber(float $valor, bool $upper = false): string
    {
        return self::spell(
            $valor,
            ['', '', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão'],
            ['', '', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões'],
            $upper,
        );
    }

    /**
     * Preenche a string até o tamanho dado. (ex-getNumZeros)
     *
     * @param string $pos 'L' esquerda, 'R' direita, 'B' ambos.
     */
    public static function pad(string $value, int $length, string $char = '0', string $pos = 'L'): string
    {
        return match (strtoupper($pos)) {
            'R' => str_pad($value, $length, $char, STR_PAD_RIGHT),
            'B' => str_pad($value, $length, $char, STR_PAD_BOTH),
            default => str_pad($value, $length, $char, STR_PAD_LEFT),
        };
    }

    /**
     * Converte uma string monetária BR para o formato de máquina. (ex-formatMoney)
     * Remove o separador de milhar e troca o decimal por ponto.
     *
     * @example formatMoney('1.234,56') retorna '1234.56'
     */
    public static function formatMoney(string $value, string $decimal = ',', string $milhares = '.'): string
    {
        if (preg_match('/[' . preg_quote($decimal, '/') . ']/', $value)) {
            return str_replace($decimal, $milhares, str_replace($milhares, '', $value));
        }

        return $value;
    }

    /**
     * Faz o parse de uma string monetária BR para float. (substitui validaCampoValue)
     *
     * @example parseMoney('1.234,56') retorna 1234.56
     */
    public static function parseMoney(string $value): float
    {
        return (float) self::formatMoney($value);
    }

    /**
     * Formata um tamanho em bytes de forma legível. (ex-getFormatSize, corrigido)
     *
     * O v1 estava bugado: só dividia com `$size > 1024` (perdendo o próprio 1024)
     * e truncava com substr(strpos+3), gerando "102 B" para 1024. Aqui a divisão
     * é feita corretamente e os zeros decimais à toa são removidos.
     *
     * @example formatBytes(1536) retorna '1.5 KB'
     */
    public static function formatBytes(float $size, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $last = count($units) - 1;
        $i = 0;

        while ($size >= 1024 && $i < $last) {
            $size /= 1024;
            $i++;
        }

        $formatted = rtrim(rtrim(number_format($size, $decimals, '.', ''), '0'), '.');

        return $formatted . ' ' . $units[$i];
    }

    # ------------------------------------------------------------------- PRIVATE

    /**
     * Núcleo do "por extenso". O v1 tinha isto duplicado em extensoMoeda e
     * extensoNumero, diferindo só nos rótulos singular/plural. Também corrige o
     * modo maiúsculas: o v1 usava preg_replace(" E ", ...) sem delimitador (que
     * retornava null); aqui é str_replace. O espaçamento é normalizado no fim.
     *
     * @param list<string> $singular
     * @param list<string> $plural
     */
    private static function spell(float $valor, array $singular, array $plural, bool $upper): string
    {
        $c = ['', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
        $d = ['', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $d10 = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezesete', 'dezoito', 'dezenove'];
        $u = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];

        $z = 0;
        $rt = '';

        // number_format com "." como decimal E milhar quebra o valor em grupos de 3.
        $inteiro = explode('.', number_format($valor, 2, '.', '.'));

        foreach ($inteiro as $k => $grupo) {
            $inteiro[$k] = str_pad($grupo, 3, '0', STR_PAD_LEFT);
        }

        $fim = count($inteiro) - ($inteiro[count($inteiro) - 1] > 0 ? 1 : 2);

        for ($i = 0; $i < count($inteiro); $i++) {
            $grupo = $inteiro[$i];

            $rc = (($grupo > 100) && ($grupo < 200)) ? 'cento' : $c[$grupo[0]];
            $rd = ($grupo[1] < 2) ? '' : $d[$grupo[1]];
            $ru = ($grupo > 0) ? (($grupo[1] == 1) ? $d10[$grupo[2]] : $u[$grupo[2]]) : '';

            $r = $rc . (($rc && ($rd || $ru)) ? ' e ' : '') . $rd . (($rd && $ru) ? ' e ' : '') . $ru;

            $t = count($inteiro) - 1 - $i;
            $r .= $r ? ' ' . ($grupo > 1 ? $plural[$t] : $singular[$t]) : '';

            if ($grupo == '000') {
                $z++;
            } elseif ($z > 0) {
                $z--;
            }

            if (($t == 1) && ($z > 0) && ($inteiro[0] > 0)) {
                $r .= (($z > 1) ? ' de ' : '') . $plural[$t];
            }

            if ($r) {
                $liga = (($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? (($i < $fim) ? ', ' : ' e ') : ' ';
                $rt .= $liga . $r;
            }
        }

        $rt = trim(preg_replace('/\s+/', ' ', $rt));

        if ($rt === '') {
            return $upper ? 'Zero' : 'zero';
        }

        return $upper ? str_replace(' E ', ' e ', ucwords($rt)) : $rt;
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Tools (numeros) -> Cubo\Tools\Number
 *
 * RENOMEADOS
 *   extensoMoeda -> spellCurrency
 *   extensoNumero -> spellNumber
 *   getNumZeros -> pad
 *   getFormatSize -> formatBytes
 *   formatMoney -> formatMoney
 *
 * MUDOU
 *   validaCampoValue -> parseMoney -- le valor no formato BR
 *
 * MOVIDOS
 *   transformMethod -> Cubo\Routing\Router
 */
