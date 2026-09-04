<?php

namespace Cubo\Validation;

use Cubo\Tools\Date;
use Cubo\Tools\Number;

/**
 * Normaliza dados crus contra filtros declarativos, antes de validar.
 *
 * O Validator responde "esse dado presta?"; este responde "esse dado, na forma
 * que o formulario mandou, vira o que?". Sao passos diferentes: sanitiza e
 * depois valida, senao a regra `numeric` reprova um "1.234,56" que era valido.
 *
 * Contrato de ausencia, igual para todo filtro:
 * - campo fora do array continua fora (quem cobra falta e o `required`);
 * - campo presente e vazio vira null;
 * - campo presente e impossivel de converter vira null e registra erro.
 *
 * Os filtros de texto (trim, upper, lower, digits) fogem do meio dessa regra de
 * proposito: sao transformacao pura de string e devolvem '' em vez de null.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
class Sanitizer
{
    /** Filtros aceitos; nome fora daqui e erro de quem escreveu a regra. */
    private const FILTROS = [
        'trim', 'money', 'date', 'int', 'float',
        'digits', 'upper', 'lower',
    ];

    private const CASAS_PADRAO = 2;

    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $original o cru, guardado para sanitize() poder repetir
     * @param array<string, string> $rules campo => 'trim|money'
     */
    public function __construct(
        private array $original,
        private array $rules,
    ) {
        $this->data = $original;
    }

    /** @return bool true quando nenhum campo caiu no caminho do erro */
    public function sanitize(): bool
    {
        // recomeca do cru: rodar de novo em cima do ja convertido daria outro
        // resultado, e um valor que virou null esconderia o erro da primeira vez
        $this->data = $this->original;
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            if (!array_key_exists($field, $this->data)) {
                continue;
            }

            foreach (explode('|', $ruleString) as $rule) {
                $rule = trim($rule);

                // null e ponto final: filtro seguinte nao tem o que converter
                if ($this->data[$field] === null) {
                    break;
                }

                if ($rule !== '') {
                    $this->data[$field] = $this->applyFilter($field, $this->data[$field], $rule);
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * O array inteiro, e nao so os campos declarados: sanitizar dois campos de
     * dez nao pode sumir com os outros oito.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /** @return array<string, list<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string, string> */
    public function getErrorsFlat(): array
    {
        $flat = [];

        foreach ($this->errors as $field => $messages) {
            $flat[$field] = $messages[0];
        }

        return $flat;
    }

    # ------------------------------------------------------------------- PRIVATE

    private function applyFilter(string $field, mixed $value, string $rule): mixed
    {
        [$name, $param] = $this->parseRule($rule);

        if (!in_array($name, self::FILTROS, true)) {
            throw new \InvalidArgumentException(
                "Filtro de sanitizacao desconhecido: '{$name}' (campo '{$field}'). "
                . 'Conhecidos: ' . implode(', ', self::FILTROS) . '.'
            );
        }

        // array e objeto chegam aqui quando o formulario manda `campo[]` num
        // campo escalar; converter na marra viraria "Array" ou fatal
        if (!is_scalar($value)) {
            $this->errors[$field][] = "{$field} nao aceita valor composto";

            return null;
        }

        $texto = (string) $value;

        return match ($name) {
            'trim' => trim($texto),
            'upper' => mb_strtoupper($texto),
            'lower' => mb_strtolower($texto),
            'digits' => preg_replace('/\D/', '', $texto) ?? '',
            'money' => $this->filterMoney($field, $texto, $param),
            'date' => $this->filterDate($field, $texto, $param),
            'int' => $this->filterInt($field, $texto),
            'float' => $this->filterFloat($field, $texto),
        };
    }

    /** @return array{0: string, 1: string|null} */
    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $param] = explode(':', $rule, 2);

            return [trim($name), trim($param)];
        }

        return [trim($rule), null];
    }

    /** @param string|null $param numero de casas decimais; o padrao e 2 */
    private function filterMoney(string $field, string $value, ?string $param): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        // sem digito nenhum o parseMoney devolveria 0.0, que mente: o usuario
        // digitou algo, so nao era dinheiro
        if (!preg_match('/\d/', $value)) {
            $this->errors[$field][] = "{$field} nao e um valor monetario valido";

            return null;
        }

        return Number::toDecimal($value, $param === null ? self::CASAS_PADRAO : (int) $param);
    }

    /** @param string|null $param apelido de formato do Date::convert; o padrao e 'eng' */
    private function filterDate(string $field, string $value, ?string $param): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $convertida = Date::tryConvert($value, $param ?? 'eng');

        if ($convertida === null) {
            $this->errors[$field][] = "{$field} nao e uma data valida";
        }

        return $convertida;
    }

    private function filterInt(string $field, string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // "3.7" nao vira 3 calado: truncar por conta propria perde dado sem avisar
        if (!preg_match('/^-?\d+$/', $value)) {
            $this->errors[$field][] = "{$field} deve ser um numero inteiro";

            return null;
        }

        return (int) $value;
    }

    private function filterFloat(string $field, string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // sintaxe numerica do PHP, com ponto decimal; para entrada BR use money
        if (!is_numeric($value)) {
            $this->errors[$field][] = "{$field} deve ser um numero";

            return null;
        }

        return (float) $value;
    }
}
