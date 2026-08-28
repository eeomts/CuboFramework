<?php

namespace Cubo\Tests\Unit\Database\Migrations;

use Cubo\Database\Db;
use Cubo\Database\Migrations\Migrator;
use Cubo\Exceptions\CuboException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Migrator::class)]
final class MigratorTest extends TestCase
{
    private string $pasta;

    protected function setUp(): void
    {
        Db::getInstance()->addConnection('migrator-test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Db::getInstance()->changeConnection('migrator-test');

        $this->pasta = sys_get_temp_dir() . '/cubo-migrations-' . bin2hex(random_bytes(4));
        mkdir($this->pasta, 0777, true);

        $this->limparBanco();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->pasta . '/*.php') ?: [] as $arquivo) {
            unlink($arquivo);
        }
        @rmdir($this->pasta);

        $this->limparBanco();
    }

    private function limparBanco(): void
    {
        $schema = Db::getInstance()->getConnection()->getSchemaBuilder();

        foreach ([Migrator::TABELA, 'clientes', 'pedidos'] as $tabela) {
            $schema->dropIfExists($tabela);
        }
    }

    private function migrator(): Migrator
    {
        return new Migrator(Db::getInstance()->getConnection(), $this->pasta);
    }

    /** Escreve uma migration em disco, como o desenvolvedor faria. */
    private function escrever(string $nome, string $tabela): void
    {
        file_put_contents($this->pasta . '/' . $nome . '.php', <<<PHP
        <?php
        use Cubo\\Database\\Migrations\\Migration;
        use Cubo\\Database\\Migrations\\Schema;
        use Illuminate\\Database\\Schema\\Blueprint;

        return new class extends Migration {
            public function up(Schema \$schema): void
            {
                \$schema->create('{$tabela}', function (Blueprint \$t) {
                    \$t->id();
                    \$t->string('nome');
                    \$t->dateTime('created');
                    \$t->dateTime('updated');
                    \$t->tinyInteger('deleted');
                });
            }

            public function down(Schema \$schema): void
            {
                \$schema->dropIfExists('{$tabela}');
            }
        };
        PHP);
    }

    public function testSubirCriaATabelaEregistraOQueRodou(): void
    {
        $this->escrever('2026_01_01_000000_cria_clientes', 'clientes');

        $aplicadas = $this->migrator()->subir();

        $this->assertSame(['2026_01_01_000000_cria_clientes'], $aplicadas);
        $this->assertTrue(Db::getInstance()->getConnection()->getSchemaBuilder()->hasTable('clientes'));
    }

    public function testSubirDuasVezesNaoRepete(): void
    {
        $this->escrever('2026_01_01_000000_cria_clientes', 'clientes');

        $this->migrator()->subir();
        $segunda = $this->migrator()->subir();

        $this->assertSame([], $segunda);
    }

    /** O prefixo de data no nome faz a ordem alfabetica ser a cronologica. */
    public function testAplicaNaOrdemCronologica(): void
    {
        $this->escrever('2026_02_01_000000_cria_pedidos', 'pedidos');
        $this->escrever('2026_01_01_000000_cria_clientes', 'clientes');

        $aplicadas = $this->migrator()->subir();

        $this->assertSame([
            '2026_01_01_000000_cria_clientes',
            '2026_02_01_000000_cria_pedidos',
        ], $aplicadas);
    }

    public function testPendentesListaSoOQueFalta(): void
    {
        $this->escrever('2026_01_01_000000_cria_clientes', 'clientes');
        $this->migrator()->subir();

        $this->escrever('2026_02_01_000000_cria_pedidos', 'pedidos');

        $this->assertSame(['2026_02_01_000000_cria_pedidos'], $this->migrator()->pendentes());
    }

    // --- lote e rollback ---

    /** O rollback desfaz o LOTE, nao um arquivo: e como se desfaz um deploy. */
    public function testDesfazerVoltaOLoteInteiro(): void
    {
        $this->escrever('2026_01_01_000000_cria_clientes', 'clientes');
        $this->escrever('2026_02_01_000000_cria_pedidos', 'pedidos');

        $this->migrator()->subir();
        $desfeitas = $this->migrator()->desfazer();

        $this->assertCount(2, $desfeitas);

        $schema = Db::getInstance()->getConnection()->getSchemaBuilder();
        $this->assertFalse($schema->hasTable('clientes'));
        $this->assertFalse($schema->hasTable('pedidos'));
    }

    /** Lotes separados: o rollback so mexe no ultimo. */
    public function testDesfazerNaoTocaEmLoteAnterior(): void
    {
        $this->escrever('2026_01_01_000000_cria_clientes', 'clientes');
        $this->migrator()->subir();

        $this->escrever('2026_02_01_000000_cria_pedidos', 'pedidos');
        $this->migrator()->subir();

        $desfeitas = $this->migrator()->desfazer();

        $this->assertSame(['2026_02_01_000000_cria_pedidos'], $desfeitas);

        $schema = Db::getInstance()->getConnection()->getSchemaBuilder();
        $this->assertTrue($schema->hasTable('clientes'));
        $this->assertFalse($schema->hasTable('pedidos'));
    }

    public function testDesfazerDoMaisNovoParaOMaisAntigo(): void
    {
        $this->escrever('2026_01_01_000000_cria_clientes', 'clientes');
        $this->escrever('2026_02_01_000000_cria_pedidos', 'pedidos');
        $this->migrator()->subir();

        $desfeitas = $this->migrator()->desfazer();

        $this->assertSame([
            '2026_02_01_000000_cria_pedidos',
            '2026_01_01_000000_cria_clientes',
        ], $desfeitas);
    }

    public function testDesfazerSemNadaAplicadoEhNoop(): void
    {
        $this->assertSame([], $this->migrator()->desfazer());
    }

    // --- situacao ---

    public function testSituacaoMostraOQueRodouEOQueFalta(): void
    {
        $this->escrever('2026_01_01_000000_cria_clientes', 'clientes');
        $this->migrator()->subir();
        $this->escrever('2026_02_01_000000_cria_pedidos', 'pedidos');

        $this->assertSame([
            '2026_01_01_000000_cria_clientes' => true,
            '2026_02_01_000000_cria_pedidos' => false,
        ], $this->migrator()->situacao());
    }

    public function testPastaVaziaNaoQuebra(): void
    {
        $this->assertSame([], $this->migrator()->subir());
        $this->assertSame([], $this->migrator()->situacao());
    }

    public function testArquivoQueNaoDevolveMigrationFalhaComMensagemClara(): void
    {
        file_put_contents($this->pasta . '/2026_01_01_000000_torto.php', '<?php return "nao sou migration";');

        $this->expectException(CuboException::class);
        $this->expectExceptionMessageMatches('/precisa devolver uma instancia/');

        $this->migrator()->subir();
    }

    /** A tabela de controle nasce sozinha na primeira execucao. */
    public function testATabelaDeControleEhCriadaSozinha(): void
    {
        $this->migrator()->situacao();

        $this->assertTrue(
            Db::getInstance()->getConnection()->getSchemaBuilder()->hasTable(Migrator::TABELA)
        );
    }
}
