<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Str;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Str::class)]
final class StrTest extends TestCase
{
    # ---------------------------------------------------------------- ACENTOS

    public function testRemoveAccentsPreservaOCaixa(): void
    {
        $this->assertSame('acao', Str::removeAccents('ação'));
        $this->assertSame('MATEUSC', Str::removeAccents('MATEUSÇ'));
    }

    #[DataProvider('provedorSlug')]
    public function testSlug(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Str::slug($entrada));
    }

    public static function provedorSlug(): array
    {
        return [
            'acentos e espacos' => ['matéusç manda bem', 'mateusc-manda-bem'],
            'pontuacao' => ['Olá, mundo!', 'ola-mundo'],
            'colapsa separador' => ['a---b   c', 'a-b-c'],
            'apara as pontas' => ['  fim.  ', 'fim'],
        ];
    }

    public function testSlugComSeparadorCustomizado(): void
    {
        // substitui o antigo sanitizeString (slug com '_')
        $this->assertSame('sao_paulo', Str::slug('São Paulo', '_'));
    }

    public function testSnakeMinusculoComUnderline(): void
    {
        $this->assertSame('sao_paulo', Str::snake('São Paulo'));
    }

    # ----------------------------------------------------------------- LIMPEZA

    public function testRemoveSlashes(): void
    {
        $this->assertSame('path', Str::removeSlashes('  \\path\\  '));
    }

    public function testOnlyAlphanumeric(): void
    {
        $this->assertSame('mat007', Str::onlyAlphanumeric('m-a_t 0@0#7'));
    }

    public function testRemoveQuotes(): void
    {
        $this->assertSame('a_b_c', Str::removeQuotes('a"b\'c'));
    }

    public function testCleanSpecialChars(): void
    {
        $this->assertSame('a_b_c', Str::cleanSpecialChars('a@b/c'));
    }

    # ----------------------------------------------------------------- FORMATO

    public function testFirstCharPrefixaQuandoNaoForLetra(): void
    {
        $this->assertSame('abc', Str::firstChar('abc'));
        //mete um a caso nao tenha letras no comeco
        $this->assertSame('a23', Str::firstChar('123'));
    }

    public function testChangeCase(): void
    {
        $this->assertSame('AÇÃO', Str::changeCase('ação', 1));
        $this->assertSame('ação', Str::changeCase('AÇÃO', 0));
    }

    public function testTruncateCortaEAnexaSufixo(): void
    {
        $this->assertSame('teste ...', Str::truncate('teste do mateus', 5));
    }

    public function testTruncateNaoMexeQuandoCabe(): void
    {
        $this->assertSame('curto', Str::truncate('curto', 35));
    }

    public function testMaskAplicaFormato(): void
    {
        $this->assertSame('(12) 12312-3123', Str::mask('12123123123', '(##) #####-####'));
    }

    public function testColumnToWordRemovePrefixo(): void
    {
        $this->assertSame('Cidade', Str::columnToWord('fk_cidade'));
        $this->assertSame('Nome Completo', Str::columnToWord('nome_completo'));
    }

    # ------------------------------------------------------------------ ENCODE
    //sla
    public function testCuboEncodeDecodeSaoInversos(): void
    {
        $original = 'usuario';
        $encoded = Str::cuboEncode($original);

        $this->assertNotSame($original, $encoded);
        $this->assertSame($original, Str::cuboDecode($encoded));
    }
}
