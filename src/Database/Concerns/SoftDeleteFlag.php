<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */

namespace Cubo\Database\Concerns;

use Cubo\Database\Scopes\NotDeletedScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Soft delete por FLAG: coluna `deleted` tinyint 0/1 (convencao de schema).
 *
 * Diferente do SoftDeletes do Eloquent que exige timestamp deleted_at.
 * A regra mora em NotDeletedScope e se aplica sozinha em toda query.
 */
trait SoftDeleteFlag
{
    /**
     * Coluna de exclusao logica. O Model que fugir da convencao sobrescreve:
     * `const DELETED = 'outra_coluna';`
     */
    public const DELETED = 'deleted';

    /**
     * O Eloquent chama boot<NomeDoTrait>() automaticamente ao inicializar o Model.
     */
    public static function bootSoftDeleteFlag(): void
    {
        if (static::usesSoftDelete()) {
            static::addGlobalScope(new NotDeletedScope());
        }
    }

    /**
     * Se a tabela tem a coluna de exclusao logica.
     *
     * A convencao do Cubo e que toda tabela tenha `deleted`, e por isso o Model
     * base ja usa este trait. Existem excecoes -- tabelas de vinculo puro, como
     * netflex_cliente_usuario, que so tem id, as duas chaves e created. Nelas o
     * scope global tentaria filtrar por uma coluna inexistente e toda consulta
     * quebraria. Basta sobrescrever devolvendo false.
     */
    public static function usesSoftDelete(): bool
    {
        return true;
    }

    public function getDeletedColumn(): string
    {
        return static::DELETED;
    }

    /** Nome da coluna prefixado com a tabela (evita ambiguidade em join). */
    public function getQualifiedDeletedColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedColumn());
    }

    /**
     * Marca como excluido em vez de apagar a linha.
     */
    public function delete(): bool
    {
        // Sem coluna de exclusao logica, apagar e apagar mesmo.
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
     * DELETE de verdade, sem volta.
     * Passa pelo query builder, entao nao cai no delete() sobrescrito acima.
     */
    public function forceDelete(): bool
    {
        return (bool) static::withTrashed()
            ->where($this->getKeyName(), $this->getKey())
            ->delete();
    }

    /** Consulta ignorando o soft delete (inclui os excluidos). */
    public static function withTrashed(): Builder
    {
        return static::query()->withoutGlobalScope(NotDeletedScope::class);
    }

    /** Consulta SO os excluidos (util para telas de lixeira/auditoria). */
    public static function onlyTrashed(): Builder
    {
        $model = new static();

        return static::withTrashed()->where($model->getQualifiedDeletedColumn(), 1);
    }
}
