<?php

namespace Cubo\Database\Migrations;

use Cubo\Exceptions\SchemaConventionException;
use Illuminate\Database\Schema\Blueprint;

/**
 * Verifica se a tabela declarada respeita a convenção de schema do Cubo.
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class SchemaGuard
{
    /** Obrigatorias em toda tabela, sem `_at` no fim de nenhuma. */
    private const OBRIGATORIAS = ['created', 'updated', 'deleted'];

    /** Inteiros que a convencao dispensa de prefixo. */
    private const INTEIROS_LIVRES = ['id', 'deleted'];

    private const TIPOS_DATA = ['date', 'dateTime', 'dateTimeTz', 'timestamp', 'timestampTz'];

    private const TIPOS_INTEIRO = [
        'integer', 'bigInteger', 'mediumInteger', 'smallInteger', 'tinyInteger',
    ];

    private const TIPOS_MONETARIO = ['decimal', 'float', 'double'];

    /**
     * @param bool $exigeColunasDeControle tabela de vinculo puro pode nao ter
     *                                     created/updated/deleted -- e o mesmo
     *                                     caso que sobrescreve usesSoftDelete()
     * @throws SchemaConventionException
     */
    public function validar(Blueprint $blueprint, bool $exigeColunasDeControle = true): void
    {
        $tabela = $blueprint->getTable();
        $colunas = $blueprint->getColumns();

        $nomes = array_map(static fn ($coluna): string => (string) $coluna->name, $colunas);

        $violacoes = [
            ...($exigeColunasDeControle ? $this->faltamColunasDeControle($nomes) : []),
            ...$this->nomesForaDoPadrao($nomes),
            ...$this->prefixosErrados($colunas),
            ...$this->formatoDeAuxiliar($tabela, $nomes),
        ];

        if ($violacoes !== []) {
            throw SchemaConventionException::for($tabela, $violacoes);
        }
    }

    /** @param list<string> $nomes @return list<string> */
    private function faltamColunasDeControle(array $nomes): array
    {
        $faltando = array_diff(self::OBRIGATORIAS, $nomes);

        if ($faltando === []) {
            return [];
        }

        return [
            'faltam as colunas de controle: ' . implode(', ', $faltando)
            . ". Tabela de vinculo puro pode dispensa-las com exigeColunasDeControle: false",
        ];
    }

    /** @param list<string> $nomes @return list<string> */
    private function nomesForaDoPadrao(array $nomes): array
    {
        $violacoes = [];

        foreach ($nomes as $nome) {
            if (str_ends_with($nome, '_at')) {
                $violacoes[] = "'{$nome}': o Cubo usa created/updated, sem o sufixo _at";
            }

            if ($nome !== 'id' && str_ends_with($nome, '_id')) {
                $sugestao = 'fk_' . substr($nome, 0, -3);
                $violacoes[] = "'{$nome}': chave estrangeira e prefixo, nao sufixo -- use '{$sugestao}'";
            }
        }

        return $violacoes;
    }

    /**
     * @param list<\Illuminate\Database\Schema\ColumnDefinition> $colunas
     * @return list<string>
     */
    private function prefixosErrados(array $colunas): array
    {
        $violacoes = [];

        foreach ($colunas as $coluna) {
            $nome = (string) $coluna->name;
            $tipo = (string) $coluna->type;

            if (in_array($nome, self::OBRIGATORIAS, true) || $nome === 'id') {
                continue;
            }

            if (in_array($tipo, self::TIPOS_DATA, true) && !str_starts_with($nome, 'data_')) {
                $violacoes[] = "'{$nome}' e {$tipo}: coluna de data comeca com 'data_ seu burro,  consulta o guia de migracao'";
            }

            if (in_array($tipo, self::TIPOS_MONETARIO, true) && !str_starts_with($nome, 'mon_')) {
                $violacoes[] = "'{$nome}' e {$tipo}: coluna monetaria comeca com 'mon_ seu burro', consulta o guia de migracao";
            }

            if (in_array($tipo, self::TIPOS_INTEIRO, true) && !$this->inteiroValido($nome)) {
                $violacoes[] = "'{$nome}' e {$tipo}: inteiro comeca com 'fk_' ou 'num_ seu burro, consulta o guia de migracao'";
            }
        }

        return $violacoes;
    }

    private function inteiroValido(string $nome): bool
    {
        return in_array($nome, self::INTEIROS_LIVRES, true)
            || str_starts_with($nome, 'fk_')
            || str_starts_with($nome, 'num_');
    }

    /**
     * Tabela `_aux` e lista fechada no lugar de enum: id + nome.
     *
     * @param list<string> $nomes
     * @return list<string>
     */
    private function formatoDeAuxiliar(string $tabela, array $nomes): array
    {
        if (!str_ends_with($tabela, '_aux')) {
            return [];
        }

        $faltando = array_diff(['id', 'nome'], $nomes);

        return $faltando === []
            ? []
            : ["tabela _aux e lista fechada (id + nome); faltam: " . implode(', ', $faltando)];
    }
}
