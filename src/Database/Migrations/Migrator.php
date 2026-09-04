<?php

namespace Cubo\Database\Migrations;

use Cubo\Exceptions\CuboException;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Descobre as migrations, registra o que ja rodou e aplica ou desfaz.
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class Migrator
{
    public const TABELA = 'cubo_migrations';

    private readonly Schema $schema;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $diretorio,
        ?Schema $schema = null,
    ) {
        
        $this->schema = $schema ?? new Schema($connection);
    }

    /**
     * @return list<string> nomes aplicados, na ordem
     */
    public function subir(): array
    {
        $this->garantirTabelaDeControle();

        $pendentes = $this->pendentes();

        if ($pendentes === []) {
            return [];
        }

        $lote = $this->ultimoLote() + 1;
        $aplicadas = [];

        foreach ($pendentes as $nome) {
            $this->carregar($nome)->up($this->schema);

            $this->connection->table(self::TABELA)->insert([
                'migration' => $nome,
                'num_lote' => $lote,
                'created' => date('Y-m-d H:i:s'),
            ]);

            $aplicadas[] = $nome;
        }

        return $aplicadas;
    }

    /**
     * @return list<string> do mais novo para o mais antigo
     */
    public function desfazer(): array
    {
        $this->garantirTabelaDeControle();

        $lote = $this->ultimoLote();

        if ($lote === 0) {
            return [];
        }

        $nomes = $this->connection->table(self::TABELA)
            ->where('num_lote', $lote)
            ->orderBy('migration', 'desc')
            ->pluck('migration')
            ->all();

        $desfeitas = [];

        foreach ($nomes as $nome) {
            $this->carregar((string) $nome)->down($this->schema);

            $this->connection->table(self::TABELA)->where('migration', $nome)->delete();

            $desfeitas[] = (string) $nome;
        }

        return $desfeitas;
    }

    /**
     * @return array<string, bool> nome => ja rodou
     */
    public function situacao(): array
    {
        $this->garantirTabelaDeControle();

        $aplicadas = $this->aplicadas();
        $situacao = [];

        foreach ($this->arquivos() as $nome) {
            $situacao[$nome] = in_array($nome, $aplicadas, true);
        }

        return $situacao;
    }

    /** @return list<string> */
    public function pendentes(): array
    {
        $aplicadas = $this->aplicadas();

        return array_values(array_filter(
            $this->arquivos(),
            static fn (string $nome): bool => !in_array($nome, $aplicadas, true)
        ));
    }

    /**
     * O prefixo de data no nome do arquivo (2026_08_27_143000_x.php) faz a
     * ordem alfabetica ser a ordem cronologica.
     *
     * @return list<string>
     */
    public function arquivos(): array
    {
        if (!is_dir($this->diretorio)) {
            return [];
        }

        $encontrados = glob(rtrim($this->diretorio, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];

        $nomes = array_map(
            static fn (string $caminho): string => basename($caminho, '.php'),
            $encontrados
        );

        sort($nomes);

        return $nomes;
    }

    /** @return list<string> */
    private function aplicadas(): array
    {
        return array_map(
            static fn ($linha): string => (string) $linha,
            $this->connection->table(self::TABELA)->orderBy('migration')->pluck('migration')->all()
        );
    }

    private function ultimoLote(): int
    {
        return (int) ($this->connection->table(self::TABELA)->max('num_lote') ?? 0);
    }

    /** @throws CuboException se o arquivo nao devolver uma Migration */
    private function carregar(string $nome): Migration
    {
        $caminho = rtrim($this->diretorio, '/\\') . DIRECTORY_SEPARATOR . $nome . '.php';

        if (!is_file($caminho)) {
            throw new CuboException("Migration nao encontrada em disco: {$caminho}");
        }

        $migration = require $caminho;

        if (!$migration instanceof Migration) {
            throw new CuboException(
                "A migration '{$nome}' precisa devolver uma instancia de " . Migration::class . '.'
            );
        }

        return $migration;
    }

    private function garantirTabelaDeControle(): void
    {
        if ($this->schema->hasTable(self::TABELA)) {
            return;
        }

        $this->schema->create(
            self::TABELA,
            static function (Blueprint $tabela): void {
                $tabela->id();
                $tabela->string('migration')->unique();
                $tabela->integer('num_lote');
                $tabela->dateTime('created');
            },
            exigeColunasDeControle: false,
        );
    }
}
