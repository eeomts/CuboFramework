<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Arr;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Arr::class)]
final class ArrTest extends TestCase
{
    public function testGetBuscaValorAninhado(): void
    {
        $dados = ['a' => ['b' => ['c' => 42]]];

        $this->assertSame(42, Arr::get($dados, 'a.b.c'));
    }

    public function testGetRetornaDefaultQuandoCaminhoNaoExiste(): void
    {
        $this->assertNull(Arr::get(['a' => 1], 'a.b'));
        $this->assertSame('x', Arr::get(['a' => 1], 'z', 'x'));
    }

    public function testStripNumericKeysMantemAssociativasEPreservaLista(): void
    {
        // simula mysql_fetch_array(MYSQL_BOTH): coluna duplicada em chave num e assoc
        $linhas = [
            0 => [0 => 1, 'id' => 1, 1 => 'joao', 'nome' => 'joao'],
            1 => [0 => 2, 'id' => 2, 1 => 'ana', 'nome' => 'ana'],
        ];

        $limpo = Arr::stripNumericKeys($linhas);

        $this->assertSame([
            0 => ['id' => 1, 'nome' => 'joao'],
            1 => ['id' => 2, 'nome' => 'ana'],
        ], $limpo);
    }

    public function testDedupeByRemoveLinhasDuplicadasPorColunas(): void
    {
        $linhas = [
            ['tipo' => 'A', 'mes' => 1, 'x' => 10],
            ['tipo' => 'A', 'mes' => 1, 'x' => 99], // duplicada por (tipo, mes)
            ['tipo' => 'B', 'mes' => 1, 'x' => 20],
        ];

        $resultado = Arr::dedupeBy($linhas, ['tipo', 'mes']);

        $this->assertCount(2, $resultado);
        $this->assertSame(10, $resultado[0]['x']); // mantém a primeira ocorrência
        $this->assertSame('B', $resultado[1]['tipo']);
    }

    public function testCapitalizeJoin(): void
    {
        $this->assertSame('HotDogQuentinho', Arr::capitalizeJoin(['hot', 'dog', 'quentinho']));
    }

    public function testContainsRecursiveEncontraEmQualquerProfundidade(): void
    {
        $haystack = ['a', ['b', ['c', 'alvo']]];

        $this->assertTrue(Arr::containsRecursive('alvo', $haystack));
        $this->assertFalse(Arr::containsRecursive('nao-existe', $haystack));
    }

    public function testDumpImprimeSemEncerrar(): void
    {
        ob_start();
        Arr::dump(['x' => 1]);
        $saida = ob_get_clean();

        // se desse exit, o ob_get_clean nem seria alcançado
        $this->assertStringContainsString('<pre>', $saida);
        $this->assertStringContainsString('[x] => 1', $saida);
    }
}
