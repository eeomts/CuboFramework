<?php

namespace Cubo\Tests\Support;

use Cubo\Database\Db;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use PHPUnit\Framework\TestCase;

/**
 * Base dos testes que precisam de banco.
 *
 * Usa sqlite :memory: -- o banco nasce e morre dentro do teste, entao nao depende
 * de um MySQL no ar nem suja dado nenhum. O Db do Cubo e o mesmo de producao;
 * so a conexao registrada e outra (e a prova de que o Db nao esta amarrado ao MySQL).
 */
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Db::getInstance()->addConnection('testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->createSchema();
    }

    protected function schema(): Builder
    {
        return Db::getInstance()->getConnection()->getSchemaBuilder();
    }

    protected function db(): Db
    {
        return Db::getInstance();
    }

    /**
     * Recria as tabelas a cada teste. O Capsule cacheia a conexao, entao o mesmo
     * :memory: sobrevive entre os testes -- o drop garante o isolamento.
     */
    private function createSchema(): void
    {
        $schema = $this->schema();

        $schema->dropIfExists('cliente');
        $schema->dropIfExists('cidade');

        $schema->create('cidade', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
            $table->integer('deleted')->nullable()->default(0);
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });

        $schema->create('cliente', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
            $table->integer('fk_cidade')->nullable();
            $table->decimal('mon_limite', 10, 2)->nullable();
            $table->date('data_cadastro')->nullable();
            $table->integer('deleted')->nullable()->default(0);
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }
}
