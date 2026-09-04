<?php

namespace Cubo\Validation;

use Cubo\Tools\Docs;

/**
 * Valida dados contra regras declarativas.
 *
 * @package Cubo
 */
class Validator
{
    private const REGRAS = [
        'required', 'email', 'min', 'max',
        'numeric', 'cpf', 'cnpj', 'url', 'confirmed',
    ];

    private array $errors = [];

    public function __construct(
        private array $data,
        private array $rules,
    ) {
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;

            foreach (explode('|', $ruleString) as $rule) {
                $rule = trim($rule);

                if ($rule !== '') {
                    $this->applyRule($field, $value, $rule);
                }
            }
        }

        return empty($this->errors);
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = $this->parseRule($rule);

        # nome errado estoura mesmo que o campo ja tenha reprovado numa regra anterior
        if (!in_array($name, self::REGRAS, true)) {
            throw new \InvalidArgumentException(
                "Regra de validacao desconhecida: '{$name}' (campo '{$field}'). "
                . 'Conhecidas: ' . implode(', ', self::REGRAS) . '.'
            );
        }

        match ($name) {
            'required' => $this->validateRequired($field, $value),
            'email' => $this->validateEmail($field, $value),
            'min' => $this->validateMin($field, $value, (int) $param),
            'max' => $this->validateMax($field, $value, (int) $param),
            'numeric' => $this->validateNumeric($field, $value),
            'cpf' => $this->validateCpf($field, $value),
            'cnpj' => $this->validateCnpj($field, $value),
            'url' => $this->validateUrl($field, $value),
            'confirmed' => $this->validateConfirmed($field, $value),
        };
    }

    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $param] = explode(':', $rule, 2);
            return [trim($name), trim($param)];
        }
        return [trim($rule), null];
    }

    private function validateRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->errors[$field][] = "{$field} é obrigatório";
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "{$field} deve ser um email válido";
        }
    }

    private function validateMin(string $field, mixed $value, int $min): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->tamanho($value) < $min) {
            $this->errors[$field][] = is_array($value)
                ? "{$field} deve ter no mínimo {$min} itens"
                : "{$field} deve ter no mínimo {$min} caracteres";
        }
    }

    private function validateMax(string $field, mixed $value, int $max): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->tamanho($value) > $max) {
            $this->errors[$field][] = is_array($value)
                ? "{$field} deve ter no máximo {$max} itens"
                : "{$field} deve ter no máximo {$max} caracteres";
        }
    }

    private function validateNumeric(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_numeric($value)) {
            $this->errors[$field][] = "{$field} deve ser numérico";
        }
    }

    private function validateCpf(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!Docs::isCpf((string) $value)) {
            $this->errors[$field][] = "{$field} é um CPF inválido";
        }
    }

    private function validateCnpj(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!Docs::isCnpj((string) $value)) {
            $this->errors[$field][] = "{$field} é um CNPJ inválido";
        }
    }

    private function validateUrl(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = "{$field} deve ser uma URL válida";
        }
    }

    private function validateConfirmed(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $confirmField = "{$field}_confirmation";
        $confirmValue = $this->data[$confirmField] ?? null;

        if ($value !== $confirmValue) {
            $this->errors[$field][] = "{$field} não corresponde à confirmação";
        }
    }

    /** Array conta elementos; o resto conta caracteres, nao bytes. */
    private function tamanho(mixed $value): int
    {
        return is_array($value) ? count($value) : mb_strlen((string) $value);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorsFlat(): array
    {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            $flat[$field] = is_array($messages) ? $messages[0] : $messages;
        }
        return $flat;
    }
}
