<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */

namespace Cubo\Database\Concerns;

use Cubo\Database\Scopes\NotDeletedScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Soft delete por FLAG: coluna `deleted` tinyint 0/1
 */
trait SoftDeleteFlag
{
    public const DELETED = 'deleted';

    public static function bootSoftDeleteFlag(): void
    {
        if (static::usesSoftDelete()) {
            static::addGlobalScope(new NotDeletedScope());
        }
    }

    public static function usesSoftDelete(): bool
    {
        return true;
    }

    public function getDeletedColumn(): string
    {
        return static::DELETED;
    }

    public function getQualifiedDeletedColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedColumn());
    }

    public function delete(): bool
    {
        if (!static::usesSoftDelete()) {
            return (bool) parent::delete();
        }

        $this->{$this->getDeletedColumn()} = 1;

        return $this->save();
    }

    /** Traz de volta um registro excluido (nao existia no v1). */
    public function restore(): bool
    {
        $this->{$this->getDeletedColumn()} = 0;

        return $this->save();
    }

    public function trashed(): bool
    {
        return (int) $this->{$this->getDeletedColumn()} === 1;
    }

    /**
     * DELETE de verdade, sem volta. passa por riba de qualquer coisa
     */
    public function forceDelete(): bool
    {
        return (bool) static::withTrashed()
            ->where($this->getKeyName(), $this->getKey())
            ->delete();
    }

    /** ignora o soft delete*/
    public static function withTrashed(): Builder
    {
        return static::query()->withoutGlobalScope(NotDeletedScope::class);
    }

    /** apenas os excluidos*/
    public static function onlyTrashed(): Builder
    {
        $model = new static();

        return static::withTrashed()->where($model->getQualifiedDeletedColumn(), 1);
    }
}
