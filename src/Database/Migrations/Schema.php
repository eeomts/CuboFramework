<?php

namespace Cubo\Database\Migrations;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * O que a migration usa para mexer no schema.
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class Schema
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaGuard $guarda = new SchemaGuard(),
    ) {
    }

    /**
     * Cria a tabela.
     * @param bool $exigeColunasDeControle false dispensa created/updated/deleted, para tabela de vinculo puro
     * @param bool $validarConvencao escotilha de fuga; desligar deixa passar qualquer nome, e o custo e perder os automatismos de SearchCriteria e SoftDelete
     */
    public function create(
        string $tabela,
        Closure $definicao,
        bool $exigeColunasDeControle = true,
        bool $validarConvencao = true,
    ): void {
        $blueprint = new Blueprint($tabela);
        $blueprint->create();

        $definicao($blueprint);

        if ($validarConvencao) {
            $this->guarda->validar($blueprint, $exigeColunasDeControle);
        }

        $this->executar($blueprint);
    }

    public function table(string $tabela, Closure $definicao, bool $validarConvencao = true): void
    {
        $blueprint = new Blueprint($tabela);

        $definicao($blueprint);

        if ($validarConvencao) {
            $this->guarda->validar($blueprint, exigeColunasDeControle: false);
        }

        $this->executar($blueprint);
    }

    public function drop(string $tabela): void
    {
        $blueprint = new Blueprint($tabela);
        $blueprint->drop();

        $this->executar($blueprint);
    }

    public function dropIfExists(string $tabela): void
    {
        $blueprint = new Blueprint($tabela);
        $blueprint->dropIfExists();

        $this->executar($blueprint);
    }

    public function hasTable(string $tabela): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable($tabela);
    }

    public function hasColumn(string $tabela, string $coluna): bool
    {
        return $this->connection->getSchemaBuilder()->hasColumn($tabela, $coluna);
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    private function executar(Blueprint $blueprint): void
    {
        $blueprint->build($this->connection, $this->connection->getSchemaGrammar());
    }
}
