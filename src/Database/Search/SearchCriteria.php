<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */

namespace Cubo\Database\Search;

use Cubo\Tools\Date;
use Illuminate\Database\Eloquent\Builder;

/**
 * Traduz o $_POST dos filtros de grid em condicoes de busca.
 * Convencoes de nome de campo (contrato dos grids):
 * - "tabela:coluna" -> "tabela.coluna"
 * - fk_* -> igualdade exata; array vira whereIn
 * - mon_* -> valor BR ("1.234,56") normalizado para 1234.56
 * - data_*, created, updated -> normalizada para Y-m-d; sufixo _begin -> >=,
 *   _end -> <= (com hora opcional)
 * - pago -> o valor 2 significa 0
 * - qualquer outro -> LIKE %valor%
 */
final class SearchCriteria
{
    /**
     * @param array<string,mixed> $post campos do filtro (normalmente o $_POST)
     * @param array<string,string> $incrementDate hora a anexar num campo de data (ex: ' 23:59:59')
     */
    public function __construct(
        private readonly array $post,
        private readonly array $incrementDate = []
    ) {}

    public function applyTo(Builder $query): Builder
    {
        foreach ($this->post as $field => $value) {
            if (empty($value)) {
                continue;
            }

            $column = str_replace(':', '.', (string) $field);
            $name = $this->columnName($column);

            if (str_starts_with($name, 'fk_')) {
                is_array($value)
                    ? $query->whereIn($column, $value)
                    : $query->where($column, '=', $value);
                continue;
            }

            if ($this->isDate($name)) {
                $this->applyDate($query, $column, (string) $value);
                continue;
            }

            if (str_starts_with($name, 'mon_')) {
                $value = $this->toDecimal((string) $value);
            } elseif (str_contains($name, 'pago')) {
                $value = ((int) $value === 2) ? '0' : $value;
            }

            $query->where($column, 'LIKE', "%{$value}%");
        }

        return $query;
    }

    /**
     * Nome da coluna sem o nome da tabela.
     */
    private function columnName(string $column): string
    {
        if (str_contains($column, '.')) {
            return explode('.', $column)[1];
        }

        return $column;
    }

    private function isDate(string $name): bool
    {
        return str_starts_with($name, 'data_')
            || str_contains($name, 'created')
            || str_contains($name, 'updated');
    }

    private function applyDate(Builder $query, string $column, string $value): void
    {
        $date = Date::formataData($value, 'Y-m-d');
        $hour = $this->incrementDate[$column] ?? '';

        if (str_ends_with($column, '_begin')) {
            $query->where(substr($column, 0, -strlen('_begin')), '>=', $date . $hour);
            return;
        }

        if (str_ends_with($column, '_end')) {
            $query->where(substr($column, 0, -strlen('_end')), '<=', $date . $hour);
            return;
        }

        $query->where($column, 'LIKE', "%{$date}%");
    }

    # "1.234,56" (BR) -> "1234.56" (SQL) 
    private function toDecimal(string $value): string
    {
        return str_replace(',', '.', str_replace('.', '', $value));
    }
}
