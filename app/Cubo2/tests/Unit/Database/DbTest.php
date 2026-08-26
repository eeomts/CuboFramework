<?php

namespace Cubo\Tests\Unit\Database;

use Cubo\Database\Db;
use Cubo\Tests\Support\DatabaseTestCase;
use InvalidArgumentException;

class DbTest extends DatabaseTestCase
{
    public function testGetInstanceEhSingleton(): void
    {
        $this->assertSame(Db::getInstance(), Db::getInstance());
    }

    public function testAddConnectionTornaAConexaoAtiva(): void
    {
        $this->assertSame('testing', $this->db()->getCurrentConnectionName());
    }

    public function testChangeConnectionAlternaEntreConexoesRegistradas(): void
    {
        $this->db()->addConnection('outra', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->assertSame('outra', $this->db()->getCurrentConnectionName());

        $this->db()->changeConnection('testing');
        $this->assertSame('testing', $this->db()->getCurrentConnectionName());
    }

    public function testChangeConnectionRecusaConexaoInexistente(): void
    {
        // o v1 engolia a excecao no ErrorManager e seguia com a conexao errada
        $this->expectException(InvalidArgumentException::class);

        $this->db()->changeConnection('nao_existe');
    }

    public function testExecuteSqlSemBindingsMantemCompatibilidadeComOLegado(): void
    {
        $this->db()->executeSql("INSERT INTO cidade (nome) VALUES ('Curitiba')");

        // os ~1600 callers legados encadeiam ->fetchAll() no retorno
        $rows = $this->db()->executeSql('SELECT nome FROM cidade')->fetchAll();

        $this->assertSame('Curitiba', $rows[0]['nome']);
    }

    public function testExecuteSqlComBindingsFiltraOValor(): void
    {
        $this->db()->executeSql('INSERT INTO cidade (nome) VALUES (?)', ['Curitiba']);
        $this->db()->executeSql('INSERT INTO cidade (nome) VALUES (?)', ['Londrina']);

        $rows = $this->db()->executeSql('SELECT nome FROM cidade WHERE nome = ?', ['Londrina'])->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertSame('Londrina', $rows[0]['nome']);
    }

    /**
     * O ponto do REFAC 6: o WAF (Cubo_Security::filterValue) morreu no REFAC 2,
     * entao o bind e a unica defesa. Com bind, o payload e tratado como VALOR,
     * nunca como SQL -- a tabela continua de pe.
     */
    public function testExecuteSqlComBindingsNeutralizaSqlInjection(): void
    {
        $this->db()->executeSql('INSERT INTO cidade (nome) VALUES (?)', ['Curitiba']);

        $payload = "Curitiba'; DROP TABLE cidade; --";
        $rows = $this->db()->executeSql('SELECT nome FROM cidade WHERE nome = ?', [$payload])->fetchAll();

        $this->assertSame([], $rows, 'o payload deve ser comparado como texto, sem casar com nada');
        $this->assertTrue($this->schema()->hasTable('cidade'), 'a tabela nao pode ter sido dropada');
    }

    public function testGetLastInsertIdDevolveOIdGerado(): void
    {
        $this->db()->executeSql('INSERT INTO cidade (nome) VALUES (?)', ['Curitiba']);

        $this->assertSame('1', $this->db()->getLastInsertId());
    }

    public function testTruncateEsvaziaATabela(): void
    {
        $this->db()->executeSql('INSERT INTO cidade (nome) VALUES (?)', ['Curitiba']);

        $this->db()->truncate('cidade');

        $rows = $this->db()->executeSql('SELECT nome FROM cidade')->fetchAll();
        $this->assertSame([], $rows);
    }
}
