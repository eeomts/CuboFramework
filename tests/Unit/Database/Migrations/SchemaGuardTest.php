<?php

namespace Cubo\Tests\Unit\Database\Migrations;

use Closure;
use Cubo\Database\Migrations\SchemaGuard;
use Cubo\Exceptions\SchemaConventionException;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaGuard::class)]
final class SchemaGuardTest extends TestCase
{
    private SchemaGuard $guarda;

    protected function setUp(): void
    {
        $this->guarda = new SchemaGuard();
    }

    private function tabela(string $nome, Closure $definicao): Blueprint
    {
        $blueprint = new Blueprint($nome);
        $blueprint->create();
        $definicao($blueprint);

        return $blueprint;
    }

    /** Acrescenta o trio de controle, para o teste focar no que interessa. */
    private function comControle(Blueprint $t): void
    {
        $t->dateTime('created');
        $t->dateTime('updated');
        $t->tinyInteger('deleted');
    }

    public function testTabelaDentroDaConvencaoPassa(): void
    {
        $blueprint = $this->tabela('clientes', function (Blueprint $t): void {
            $t->id();
            $t->string('nome');
            $t->integer('fk_cidade');
            $t->dateTime('data_nascimento');
            $t->decimal('mon_limite', 10, 2);
            $t->integer('num_filhos');
            $this->comControle($t);
        });

        $this->guarda->validar($blueprint);

        $this->expectNotToPerformAssertions();
    }

    // --- colunas de controle ---

    public function testFaltandoColunasDeControleReprova(): void
    {
        $blueprint = $this->tabela('clientes', function (Blueprint $t): void {
            $t->id();
            $t->string('nome');
        });

        $this->expectException(SchemaConventionException::class);
        $this->expectExceptionMessageMatches('/created, updated, deleted/');

        $this->guarda->validar($blueprint);
    }

    /** Tabela de vinculo puro dispensa o trio -- o mesmo caso do usesSoftDelete(false). */
    public function testTabelaDeVinculoPodeDispensarOTrio(): void
    {
        $blueprint = $this->tabela('cliente_usuario_rel', function (Blueprint $t): void {
            $t->id();
            $t->integer('fk_cliente');
            $t->integer('fk_usuario');
        });

        $this->guarda->validar($blueprint, exigeColunasDeControle: false);

        $this->expectNotToPerformAssertions();
    }

    // --- os habitos do Laravel que a convencao recusa ---

    public function testSufixoAtReprova(): void
    {
        $blueprint = $this->tabela('clientes', function (Blueprint $t): void {
            $t->id();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $this->comControle($t);
        });

        $this->expectException(SchemaConventionException::class);
        $this->expectExceptionMessageMatches('/sem o sufixo _at/');

        $this->guarda->validar($blueprint);
    }

    /** O caso que o roadmap cita: cliente_id em vez de fk_cliente. */
    public function testSufixoIdReprovaEsugereOPrefixo(): void
    {
        $blueprint = $this->tabela('pedidos', function (Blueprint $t): void {
            $t->id();
            $t->integer('cliente_id');
            $this->comControle($t);
        });

        try {
            $this->guarda->validar($blueprint);
            $this->fail('deveria ter reprovado');
        } catch (SchemaConventionException $e) {
            $this->assertStringContainsString("use 'fk_cliente'", $e->getMessage());
        }
    }

    public function testAColunaIdNaoEhConfundidaComSufixoId(): void
    {
        $blueprint = $this->tabela('clientes', function (Blueprint $t): void {
            $t->id();
            $this->comControle($t);
        });

        $this->guarda->validar($blueprint);

        $this->expectNotToPerformAssertions();
    }

    // --- prefixo conforme o tipo ---

    public function testDataSemPrefixoReprova(): void
    {
        $blueprint = $this->tabela('clientes', function (Blueprint $t): void {
            $t->id();
            $t->dateTime('nascimento');
            $this->comControle($t);
        });

        try {
            $this->guarda->validar($blueprint);
            $this->fail('deveria ter reprovado');
        } catch (SchemaConventionException $e) {
            $this->assertStringContainsString('nascimento', $e->getMessage());
            $this->assertStringContainsString('data_', $e->getMessage());
        }
    }

    public function testMonetarioSemPrefixoReprova(): void
    {
        $blueprint = $this->tabela('produtos', function (Blueprint $t): void {
            $t->id();
            $t->decimal('preco', 10, 2);
            $this->comControle($t);
        });

        try {
            $this->guarda->validar($blueprint);
            $this->fail('deveria ter reprovado');
        } catch (SchemaConventionException $e) {
            $this->assertStringContainsString('preco', $e->getMessage());
            $this->assertStringContainsString('mon_', $e->getMessage());
        }
    }

    public function testInteiroSemPrefixoReprova(): void
    {
        $blueprint = $this->tabela('produtos', function (Blueprint $t): void {
            $t->id();
            $t->integer('quantidade');
            $this->comControle($t);
        });

        try {
            $this->guarda->validar($blueprint);
            $this->fail('deveria ter reprovado');
        } catch (SchemaConventionException $e) {
            $this->assertStringContainsString('quantidade', $e->getMessage());
            $this->assertStringContainsString('num_', $e->getMessage());
        }
    }

    /** created/updated sao dateTime e deleted e tinyInteger: nao levam prefixo. */
    public function testOTrioDeControleNaoPrecisaDePrefixo(): void
    {
        $blueprint = $this->tabela('clientes', function (Blueprint $t): void {
            $t->id();
            $this->comControle($t);
        });

        $this->guarda->validar($blueprint);

        $this->expectNotToPerformAssertions();
    }

    // --- tabela _aux ---

    public function testAuxSemNomeReprova(): void
    {
        $blueprint = $this->tabela('status_pagamento_aux', function (Blueprint $t): void {
            $t->id();
            $t->string('descricao');
            $this->comControle($t);
        });

        $this->expectException(SchemaConventionException::class);
        $this->expectExceptionMessageMatches('/lista fechada/');

        $this->guarda->validar($blueprint);
    }

    public function testAuxComIdENomePassa(): void
    {
        $blueprint = $this->tabela('status_pagamento_aux', function (Blueprint $t): void {
            $t->id();
            $t->string('nome');
            $this->comControle($t);
        });

        $this->guarda->validar($blueprint);

        $this->expectNotToPerformAssertions();
    }

    /** A mensagem junta todas as violacoes, para nao corrigir uma por vez. */
    public function testAMensagemListaTodasAsViolacoes(): void
    {
        $blueprint = $this->tabela('pedidos', function (Blueprint $t): void {
            $t->id();
            $t->integer('cliente_id');
            $t->decimal('total', 10, 2);
        });

        try {
            $this->guarda->validar($blueprint);
            $this->fail('deveria ter reprovado');
        } catch (SchemaConventionException $e) {
            $this->assertStringContainsString('cliente_id', $e->getMessage());
            $this->assertStringContainsString('total', $e->getMessage());
            $this->assertStringContainsString('created, updated, deleted', $e->getMessage());
        }
    }
}
