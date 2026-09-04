<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */

namespace Cubo\Database\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;


class NotDeletedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var \Cubo\Database\Model $model */
        $column = $model->getQualifiedDeletedColumn();
        
        $builder->where(function (Builder $query) use ($column) {
            $query->where($column, '!=', 1)->orWhereNull($column);
        });
    }
}
