<?php

/**
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */

namespace Cubo\Database\Concerns;

use Cubo\Database\Scopes\NotDeletedScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Soft delete por FLAG inteira: a coluna `deleted` tinyint 0/1, convencao de
 * schema do Cubo (ver Cubo\Database\Model).
 *
 * Por que nao usar o trait SoftDeletes do proprio Eloquent: ele exige uma coluna
 * `deleted_at` TIMESTAMP NULL e marca a data da exclusao.
 *
 * Ganho de design sobre o v1: la o soft delete era manual e ESPALHADO --
 * Cubo_Db::delete() ligava a flag, Cubo_Db::searchById() testava
 * `deleted == 1 ? null : $data`, e qualquer outra consulta precisava lembrar
 * de filtrar `deleted = 0` na mao (quem esquecia, trazia registro excluido).
 * Aqui a regra mora em UM lugar (NotDeletedScope) e se aplica sozinha.
 *
 * O Model pode trocar o nome da coluna com `const DELETED = 'outra_coluna';`
 */
trait SoftDeleteFlag
{
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
        return defined(static::class . '::DELETED') ? static::DELETED : 'deleted';
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
