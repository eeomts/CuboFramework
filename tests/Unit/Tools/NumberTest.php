<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Number;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Number::class)]
final class NumberTest extends TestCase
{
    # -------------------------------------------------------- EXTENSO (moeda)

    #[DataProvider('provedorMoeda')]
    public function testSpellCurrency(float $valor, string $esperado): void
    {
        // paridade com o legado (palavras), já sem os espaços à toa do v1
        $this->assertSame($esperado, Number::spellCurrency($valor));
    }

    public static function provedorMoeda(): array
    {
        return [
            'zero' => [0, 'zero'],
            'um real' => [1, 'um real'],
            'quinze' => [15, 'quinze reais'],
            'cem' => [100, 'cem reais'],
            'cento e um' => [101, 'cento e um reais'],
            'com centavos' => [1234.56, 'um mil, duzentos e trinta e quatro reais e cinquenta e seis centavos'],
            'so centavos' => [0.5, 'cinquenta centavos'],
            'milhao' => [1000000, 'um milhão de reais'],
        ];
    }

    public function testSpellCurrencyMaiusculas(): void
    {
        // o modo upper do v1 estava quebrado (preg_replace sem delimitador); aqui funciona
        $this->assertSame('Cento E Um Reais', ucwords('cento e um reais')); // sanity do ucwords
        $this->assertSame('Um Real', Number::spellCurrency(1, true));
    }

    public function testSpellNumberSemRotuloDeMoeda(): void
    {
        $this->assertSame('um mil e duzentos e trinta e quatro', Number::spellNumber(1234));
        $this->assertSame('dois', Number::spellNumber(2));
        $this->assertSame('zero', Number::spellNumber(0));
    }

    # ------------------------------------------------------------------- PAD

    public function testPad(): void
    {
        $this->assertSame('00042', Number::pad('42', 5));            // L (default)
        $this->assertSame('42...', Number::pad('42', 5, '.', 'R'));  // R
    }

    # ----------------------------------------------------------------- MONEY

    public function testFormatMoneyConverteBrParaMaquina(): void
    {
        $this->assertSame('1234.56', Number::formatMoney('1.234,56'));
    }

    public function testFormatMoneyRetornaComoEstaSemDecimal(): void
    {
        $this->assertSame('1234', Number::formatMoney('1234'));
    }

    #[DataProvider('provedorParseMoney')]
    public function testParseMoney(string $entrada, float $esperado): void
    {
        $this->assertSame($esperado, Number::parseMoney($entrada));
    }

    /** Os casos que obrigavam o app a sanitizar por fora antes de chamar. */
    public static function provedorParseMoney(): array
    {
        return [
            'mascara br' => ['1.234,56', 1234.56],
            'com simbolo e espaco' => ['R$ 1.234,56', 1234.56],
            'ponto decimal' => ['29.90', 29.9],
            'so virgula' => ['1234,56', 1234.56],
            'milhar sem centavos' => ['1.234', 1234.0],
            'milhar duplo' => ['1.234.567', 1234567.0],
            'formato us' => ['1,234.56', 1234.56],
            'negativo' => ['-45,90', -45.9],
            'sem parte inteira' => [',50', 0.5],
            'tres casas nao e milhar quando comeca com zero' => ['0.500', 0.5],
            'inteiro puro' => ['1234', 1234.0],
            'sem digito' => ['abc', 0.0],
            'vazio' => ['', 0.0],
        ];
    }

    #[DataProvider('provedorToDecimal')]
    public function testToDecimal(int|float|string|null $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Number::toDecimal($entrada));
    }

    public static function provedorToDecimal(): array
    {
        return [
            'string br' => ['R$ 1.234,5', '1234.50'],
            'float' => [29.9, '29.90'],
            'int' => [1234, '1234.00'],
            'zero e valor' => ['0', '0.00'],
            'null e ausencia' => [null, null],
            'vazio e ausencia' => ['', null],
            'so espaco e ausencia' => ['   ', null],
            'texto sem digito e ausencia' => ['abc', null],
        ];
    }

    public function testToDecimalAceitaOutrasCasas(): void
    {
        $this->assertSame('1.5000', Number::toDecimal('1,5', 4));
    }

    # ----------------------------------------------------------------- BYTES

    #[DataProvider('provedorBytes')]
    public function testFormatBytesCorrige0BugDoV1(int $bytes, string $esperado): void
    {
        $this->assertSame($esperado, Number::formatBytes($bytes));
    }

    public static function provedorBytes(): array
    {
        // valores que o v1 errava: 1024 dava "102 B", 1MB dava "102 KB"...
        return [
            'zero' => [0, '0 B'],
            'bytes' => [500, '500 B'],
            'exato 1kb' => [1024, '1 KB'],
            'meio kb' => [1536, '1.5 KB'],
            'um mb' => [1048576, '1 MB'],
            'um gb' => [1073741824, '1 GB'],
        ];
    }
}
