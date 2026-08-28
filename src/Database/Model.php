<?php

/**
 * Modelo base do sistema.
 *
 * @package Cubo
 * @author v1: Cristiano
 * @author v2: Mateus - github.com/eeomts
 */

namespace Cubo\Database;

use Cubo\Database\Concerns\SoftDeleteFlag;
use Cubo\Database\Search\SearchCriteria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Stringable;

/**
 * Diferenca central para o v1: o Doctrine_Record exigia declarar o schema inteiro
 * dentro do model (setTableDefinition() com um hasColumn() por coluna -- o Cliente
 * tinha ~400 linhas so disso). O Eloquent le as colunas da tabela em tempo de
 * execucao, entao o model so declara o que NAO da para inferir: tabela, casts,
 * fillable e relacionamentos.
 *
 * CONVENCAO DE SCHEMA DO CUBO
 *
 * O Cubo espera um banco nomeado assim, e varias partes dele decidem por esses
 * prefixos -- o SearchCriteria, por exemplo, reconhece um campo de data pelo nome.
 * Nao e suposicao acidental herdada de um projeto: e o contrato do framework.
 * Quem monta um banco fora dele perde os automatismos, nao quebra.
 *
 *   fk_<tabela>   chave estrangeira      fk_cliente, fk_cidade
 *   data_<nome>   data                   data_nascimento, data_envio
 *   num_<nome>    numero                 num_numero, num_cpf_cnpj
 *   mon_<nome>    valor monetario        mon_total
 *   created       criacao (sem _at)      preenchida sozinha, ver CREATED_AT
 *   updated       alteracao (sem _at)    preenchida sozinha, ver UPDATED_AT
 *   deleted       tinyint 0/1            soft delete, ver SoftDeleteFlag
 *
 * As tres ultimas dao para trocar por model: CREATED_AT/UPDATED_AT sao constantes
 * do proprio Eloquent, e a coluna de exclusao aceita `const DELETED = 'outra'`.
 *
 * O construtor vem do Eloquent com assinatura fixa (array $attributes = []) e
 * subclasse nao muda isso -- e o que autoriza o `new static()` do SoftDeleteFlag.
 * @phpstan-consistent-constructor
 */
abstract class Model extends EloquentModel implements Stringable
{
    use SoftDeleteFlag;

    /**
     * Colunas de auditoria. O Eloquent usa created_at/updated_at por padrao; a
     * convencao do Cubo e created/updated, e essas duas constantes reapontam o ORM
     * para elas. Com isso o preenchimento passa a ser automatico -- no v1 era o
     * Cubo_Db::salve() que setava as datas na mao (e errava: chamava
     * set_data_modificacao(), setter de uma coluna inexistente).
     */
    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $primaryKey = 'id';

    public $timestamps = true;

    /**
     * Busca pelo id respeitando o soft delete (filtro do NotDeletedScope).
     */
    public static function findById(int|string $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * Todos os registros nao excluidos.
     *
     * @return Collection<int,static>
     */
    public static function getRecords(): Collection
    {
        return static::query()->get();
    }

    /**
     * Nome de coluna -> nome de classe/relacao (fk_cliente_status -> FkClienteStatus).
     */
    public static function getColumnModel(string $fieldName): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));
    }

    /**
     * Scope de busca dos grids: Cliente::search($_POST)->get()
     *
     * A traducao mora no SearchCriteria; o model so expoe o scope.
     *
     * @param array<string,mixed> $post
     * @param array<string,string> $incrementDate
     */
    public function scopeSearch(Builder $query, array $post, array $incrementDate = []): Builder
    {
        return (new SearchCriteria($post, $incrementDate))->applyTo($query);
    }

    public function __toString(): string
    {
        return static::class . "[{$this->getKey()}]";
    }
}
