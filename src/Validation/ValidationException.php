<?php

namespace Cubo\Validation;

use Cubo\Exceptions\CuboException;

/**
 * Exceção lançada quando validação falha.
 *
 * @package Cubo
 */
class ValidationException extends CuboException
{
    public function __construct(
        private array $errors,
        string $message = 'Validation failed',
    ) {
        parent::__construct($message);
    }

    public static function withErrors(array $errors): self
    {
        $message = 'Validation failed: ' . implode(', ', array_values(array_merge(...array_values($errors))));
        return new self($errors, $message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMessagesFlat(): array
    {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            $flat[$field] = is_array($messages) ? $messages[0] : $messages;
        }
        return $flat;
    }
}
