<?php

namespace Cubo\Tests\Unit\Database;

use Cubo\Tests\Support\DatabaseTestCase;
use Cubo\Tests\Support\Models\Cliente;

class SearchCriteriaTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cliente::create(['nome' => 'Joao Silva', 'fk_cidade' => 1, 'mon_limite' => 1500.00, 'data_cadastro' => '2026-01-10']);
        Cliente::create(['nome' => 'Maria Souza', 'fk_cidade' => 2, 'mon_limite' => 250.50, 'data_cadastro' => '2026-03-20']);
        Cliente::create(['nome' => 'Joana Lima', 'fk_cidade' => 2, 'mon_limite' => 900.00, 'data_cadastro' => '2026-06-05']);
    }

    public function testCampoComumBuscaPorLike(): void
    {
        $achados = Cliente::search(['nome' => 'Joa'])->get();

        $this->assertCount(2, $achados); // Joao e Joana
    }

    public function testCampoFkBuscaPorIgualdadeExata(): void
    {
        $achados = Cliente::search(['fk_cidade' => 2])->get();

        $this->assertCount(2, $achados);
    }

    public function testCampoFkComArrayViraWhereIn(): void
    {
        // no v1 isso era um OR concatenado na string do WHERE
        $achados = Cliente::search(['fk_cidade' => [1, 2]])->get();

        $this->assertCount(3, $achados);
    }

    public function testCampoVazioEIgnorado(): void
    {
        $achados = Cliente::search(['nome' => '', 'fk_cidade' => null])->get();

        $this->assertCount(3, $achados);
    }

    public function testPrefixoDeTabelaComDoisPontosViraPonto(): void
    {
        $achados = Cliente::search(['cliente:nome' => 'Maria'])->get();

        $this->assertCount(1, $achados);
        $this->assertSame('Maria Souza', $achados->first()->nome);
    }

    public function testDataComSufixoBeginEEndDelimitamOIntervalo(): void
    {
        $achados = Cliente::search([
            'data_cadastro_begin' => '01/03/2026',
            'data_cadastro_end' => '30/06/2026',
        ])->get();

        $this->assertCount(2, $achados); // Maria (20/03) e Joana (05/06)
    }

    public function testValorMonetarioBrEhNormalizado(): void
    {
        // "1.500,00" (BR) tem que chegar no banco como 1500.00 (SQL).
        // A asserção é no binding, e não no resultado, de proposito: o v1 compara
        // dinheiro com LIKE, e o casamento final depende de como cada driver
        // formata DECIMAL (MySQL guarda "1500.00" e casa; sqlite guarda 1500 e nao).
        // O que este teste garante e a traducao BR -> SQL, que e o trabalho da classe.
        $query = Cliente::search(['mon_limite' => '1.500,00']);

        $this->assertContains('%1500.00%', $query->getBindings());
    }

    public function testABuscaEhFeitaComBindENaoConcatenada(): void
    {
        // o prepareSearch do v1 montava "nome LIKE '%{$item}%'" com o valor do $_POST,
        // entao este payload fechava a string e virava SQL
        $payload = "%' OR 1=1 --";

        $achados = Cliente::search(['nome' => $payload])->get();

        $this->assertCount(0, $achados, 'o payload deve ser buscado como texto, nao executado');
        $this->assertTrue($this->schema()->hasTable('cliente'));
    }

    public function testOSoftDeleteContinuaValendoDentroDaBusca(): void
    {
        Cliente::query()->where('nome', 'Joao Silva')->first()->delete();

        $achados = Cliente::search(['nome' => 'Joa'])->get();

        $this->assertCount(1, $achados); // so a Joana
    }
}
