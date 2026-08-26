<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Docs;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Docs::class)]
final class DocsTest extends TestCase
{
    # -------------------------------------------------------------------- CPF

    #[DataProvider('provedorCpf')]
    public function testIsCpf(string $cpf, bool $esperado): void
    {
        $this->assertSame($esperado, Docs::isCpf($cpf));
    }

    public static function provedorCpf(): array
    {
        // valores de referência conferidos com o código legado (tipo 1)
        return [
            'valido com mascara' => ['111.444.777-35', true],
            'valido sem mascara' => ['11144477735', true],
            'outro valido' => ['123.456.789-09', true],
            'digito errado' => ['12345678900', false],
            'todos iguais' => ['00000000000', false],
            'tamanho invalido' => ['1234567', false],
        ];
    }

    # ------------------------------------------------------------------- CNPJ

    #[DataProvider('provedorCnpj')]
    public function testIsCnpj(string $cnpj, bool $esperado): void
    {
        // o v1 nem rodava aqui (crashava no PHP 8); algoritmo padrão reescrito
        $this->assertSame($esperado, Docs::isCnpj($cnpj));
    }

    public static function provedorCnpj(): array
    {
        return [
            'valido com mascara' => ['11.222.333/0001-81', true],
            'valido sem mascara' => ['11222333000181', true],
            'digito errado' => ['11.222.333/0001-00', false],
            'todos iguais' => ['11111111111111', false],
            'tamanho invalido' => ['123', false],
        ];
    }

    # ---------------------------------------------------------------- DISPATCH

    public function testIsValidDetectaPeloTamanho(): void
    {
        $this->assertTrue(Docs::isValid('111.444.777-35'));   // CPF
        $this->assertTrue(Docs::isValid('11222333000181'));   // CNPJ
        $this->assertFalse(Docs::isValid('123'));             // nenhum
    }

    # ------------------------------------------------------------------ FORMAT

    public function testFormatCpf(): void
    {
        $this->assertSame('111.444.777-35', Docs::format('11144477735'));
    }

    public function testFormatCnpj(): void
    {
        $this->assertSame('11.222.333/0001-81', Docs::format('11222333000181'));
    }   

    public function testFormatDevolveDigitosQuandoTamanhoInesperado(): void
    {
        $this->assertSame('123', Docs::format('1-2-3'));
    }
}
