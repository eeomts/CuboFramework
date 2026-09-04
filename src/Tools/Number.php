<?php

namespace Cubo\Tools;

/**
 * Utilitários numéricos: valor por extenso, formatação de moeda e de bytes.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Number
{
    /** @example spellCurrency(1234.56) retorna 'um mil, duzentos e trinta e quatro reais e cinquenta e seis centavos' */
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
     * Sem rotulo de moeda.
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

    /** @param string $pos 'L' esquerda, 'R' direita, 'B' ambos */
    public static function pad(string $value, int $length, string $char = '0', string $pos = 'L'): string
    {
        return match (strtoupper($pos)) {
            'R' => str_pad($value, $length, $char, STR_PAD_RIGHT),
            'B' => str_pad($value, $length, $char, STR_PAD_BOTH),
            default => str_pad($value, $length, $char, STR_PAD_LEFT),
        };
    }

    /**
     * Nao sanitiza e nao trata milhar sozinho; para formulario use parseMoney.
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
     * Aceita simbolo, espaco, sinal e os dois separadores; o mais a direita e o
     * decimal. Sem digito nenhum devolve 0.0.
     *
     * @example parseMoney('R$ 1.234,56') retorna 1234.56
     * @example parseMoney('1,234.56') retorna 1234.56
     */
    public static function parseMoney(string $value): float
    {
        $limpo = preg_replace('/[^\d,.\-]/', '', $value) ?? '';
        $negativo = str_contains($limpo, '-');
        $limpo = str_replace('-', '', $limpo);

        if (!preg_match('/\d/', $limpo)) {
            return 0.0;
        }

        $decimal = self::decimalSeparator($limpo);

        if ($decimal === null) {
            $numero = preg_replace('/\D/', '', $limpo) ?? '';
        } else {
            $corte = (int) strrpos($limpo, $decimal);

            $numero = (preg_replace('/\D/', '', substr($limpo, 0, $corte)) ?? '')
                . '.' . (preg_replace('/\D/', '', substr($limpo, $corte + 1)) ?? '');
        }

        return (float) ($negativo ? '-' . $numero : $numero);
    }

    /**
     * String decimal no formato que o banco espera. Ausencia devolve null e nao
     * 0.00: zero e um valor, falta de valor nao e.
     *
     * @example toDecimal('R$ 1.234,5') retorna '1234.50'
     */
    public static function toDecimal(int|float|string|null $value, int $casas = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            if (!preg_match('/\d/', $value)) {
                return null;
            }

            $value = self::parseMoney($value);
        }

        return number_format((float) $value, $casas, '.', '');
    }

    /** @example formatBytes(1536) retorna '1.5 KB' */
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
     * "1.234" nao tem resposta certa: mil duzentos e trinta e quatro (mascara
     * BR) ou um inteiro e 234 milesimos. Real nao tem tres casas, entao grupo
     * de tres bem formado conta como milhar.
     */
    private static function decimalSeparator(string $value): ?string
    {
        if (preg_match('/^[1-9]\d{0,2}(\.\d{3})+$/', $value)) {
            return null;
        }

        $virgula = strrpos($value, ',');
        $ponto = strrpos($value, '.');

        return match (true) {
            $virgula !== false && ($ponto === false || $virgula > $ponto) => ',',
            $ponto !== false => '.',
            default => null,
        };
    }

    /**
     * Nucleo do spellCurrency e do spellNumber; diferem so nos rotulos.
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

        # number_format com "." como decimal E milhar quebra o valor em grupos de 3
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
