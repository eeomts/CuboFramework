<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */

namespace Cubo\Database\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Aplica deletedão implícita em TODA query por flag `deleted`. */
class NotDeletedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var \Cubo\Database\Model $model */
        $column = $model->getQualifiedDeletedColumn();

        // Fidelidade ao v1: o searchById fazia `deleted == 1 ? null : $data`, ou seja,
        // so some quem tem a flag EXATAMENTE em 1. A coluna e notnull=false, entao
        // registro antigo com deleted NULL continua visivel.
        $builder->where(function (Builder $query) use ($column) {
            $query->where($column, '!=', 1)->orWhereNull($column);
        });
    }
}
