<?php

namespace Cubo\Tests\Unit\Database;

use Cubo\Database\Model;
use Cubo\Tests\Support\DatabaseTestCase;
use Cubo\Tests\Support\Models\Cidade;
use Cubo\Tests\Support\Models\Cliente;

class ModelTest extends DatabaseTestCase
{
    public function testSaveGravaEPreencheCreatedEUpdated(): void
    {
        $cliente = new Cliente(['nome' => 'Joao']);
        $cliente->save();

        // no v1 quem setava a data era o Cubo_Db::salve(); agora e o proprio ORM
        $this->assertNotNull($cliente->created);
        $this->assertNotNull($cliente->updated);
        $this->assertSame(1, $cliente->id);
    }

    public function testFindByIdEncontraORegistro(): void
    {
        $cliente = Cliente::create(['nome' => 'Joao']);

        $achado = Cliente::findById($cliente->id);

        $this->assertNotNull($achado);
        $this->assertSame('Joao', $achado->nome);
    }

    public function testGetRecordsTrazTodosOsNaoExcluidos(): void
    {
        Cliente::create(['nome' => 'Joao']);
        Cliente::create(['nome' => 'Maria']);

        $this->assertCount(2, Cliente::getRecords());
    }

    // ---------------------------------------------------------- soft delete

    public function testDeleteApenasLigaAFlagSemApagarALinha(): void
    {
        $cliente = Cliente::create(['nome' => 'Joao']);

        $cliente->delete();

        // sumiu para o ORM...
        $this->assertNull(Cliente::findById($cliente->id));
        // ...mas a linha continua no banco
        $rows = $this->db()->executeSql('SELECT deleted FROM cliente WHERE id = ?', [$cliente->id])->fetchAll();
        $this->assertSame(1, (int) $rows[0]['deleted']);
    }

    public function testOGlobalScopeEscondeOExcluidoDeQualquerConsulta(): void
    {
        Cliente::create(['nome' => 'Joao']);
        Cliente::create(['nome' => 'Maria'])->delete();

        // o ganho sobre o v1: nenhuma dessas chamadas precisou filtrar deleted na mao
        $this->assertCount(1, Cliente::getRecords());
        $this->assertSame(1, Cliente::query()->count());
        $this->assertNull(Cliente::query()->where('nome', 'Maria')->first());
    }

    public function testRegistroComDeletedNuloContinuaVisivel(): void
    {
        // fidelidade ao v1: la so sumia quem tinha deleted == 1
        $this->db()->executeSql('INSERT INTO cliente (nome, deleted) VALUES (?, NULL)', ['Antigo']);

        $this->assertCount(1, Cliente::getRecords());
    }

    public function testTrashedInformaSeORegistroFoiExcluido(): void
    {
        $cliente = Cliente::create(['nome' => 'Joao']);
        $this->assertFalse($cliente->trashed());

        $cliente->delete();
        $this->assertTrue($cliente->trashed());
    }

    public function testWithTrashedEOnlyTrashedEnxergamOsExcluidos(): void
    {
        Cliente::create(['nome' => 'Joao']);
        Cliente::create(['nome' => 'Maria'])->delete();

        $this->assertCount(2, Cliente::withTrashed()->get());
        $this->assertCount(1, Cliente::onlyTrashed()->get());
        $this->assertSame('Maria', Cliente::onlyTrashed()->first()->nome);
    }

    public function testRestoreTrazORegistroDeVolta(): void
    {
        $cliente = Cliente::create(['nome' => 'Joao']);
        $cliente->delete();

        $cliente->restore();

        $this->assertNotNull(Cliente::findById($cliente->id));
    }

    public function testForceDeleteApagaALinhaDeVerdade(): void
    {
        $cliente = Cliente::create(['nome' => 'Joao']);

        $cliente->forceDelete();

        $rows = $this->db()->executeSql('SELECT id FROM cliente')->fetchAll();
        $this->assertSame([], $rows);
    }

    // ------------------------------------------------------- relacionamento

    public function testBelongsToCarregaORelacionado(): void
    {
        $cidade = Cidade::create(['nome' => 'Curitiba']);
        $cliente = Cliente::create(['nome' => 'Joao', 'fk_cidade' => $cidade->id]);

        $this->assertSame('Curitiba', $cliente->cidade->nome);
    }

    public function testRelacionamentoTambemRespeitaOSoftDelete(): void
    {
        $cidade = Cidade::create(['nome' => 'Curitiba']);
        $cliente = Cliente::create(['nome' => 'Joao', 'fk_cidade' => $cidade->id]);
        $cidade->delete();

        // sem o global scope isso traria a cidade excluida
        $this->assertNull(Cliente::findById($cliente->id)->cidade);
    }

    // ------------------------------------------------------------- utilidades

    public function testGetColumnModelConverteNomeDeColunaEmNomeDeClasse(): void
    {
        $this->assertSame('FkClienteStatus', Model::getColumnModel('fk_cliente_status'));
        $this->assertSame('Nome', Model::getColumnModel('nome'));
    }

    public function testToStringIdentificaOModel(): void
    {
        $cliente = Cliente::create(['nome' => 'Joao']);

        $this->assertSame(Cliente::class . '[1]', (string) $cliente);
    }
}
