<?php

namespace Cubo\Tests\Unit;

use Cubo\Security;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Security::class)]
final class SecurityTest extends TestCase
{
    public function testEscapaTagsHtmlNeutralizandoXss(): void
    {
        $entrada = '<script>alert(1)</script>';

        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            Security::escape($entrada),
        );
    }

    public function testEscapaAspasSimplesEDuplas(): void
    {
        // ENT_QUOTES: aspas de atributo não podem "escapar" do valor
        $this->assertSame(
            '&quot;x&quot; &amp; &#039;y&#039;',
            Security::escape('"x" & \'y\''),
        );
    }

    #[DataProvider('provedorTextoInofensivo')]
    public function testTextoLegitimoPassaIntacto(string $texto): void
    {
        // regressão contra o antipadrão do v1, que corrompia input legítimo
        $this->assertSame($texto, Security::escape($texto));
    }

    public static function provedorTextoInofensivo(): array
    {
        return [
            'frase comum' => ['Comprei 3 maçãs por R$ 5'],
            'contem or' => ['mateus or mateus'],
            'acentuacao' => ['tésté acentuaçãoãão'],
            'vazio' => [''],
        ];
    }

    public function testMantemUtf8ValidoESubstituiInvalido(): void
    {
        // UTF-8 válido preservado
        $this->assertSame('ação', Security::escape('ação'));

        // byte inválido (\xB1 solto) não zera a string: vira o caractere de substituição
        $resultado = Security::escape("abc\xB1def");
        $this->assertStringContainsString('abc', $resultado);
        $this->assertStringContainsString('def', $resultado);
        $this->assertNotSame('', $resultado);
    }

    public function testRandomPasswordRespeitaOTamanho(): void
    {
        $this->assertSame(10, strlen(Security::randomPassword()));
        $this->assertSame(20, strlen(Security::randomPassword(20)));
    }

    public function testRandomPasswordUsaSoOAlfabetoEsperado(): void
    {
        $senha = Security::randomPassword(200);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $senha);
    }

    public function testRandomPasswordVariaEntreChamadas(): void
    {
        // com random_int e 30 chars a colisão é praticamente impossível
        $this->assertNotSame(Security::randomPassword(30), Security::randomPassword(30));
    }
}
