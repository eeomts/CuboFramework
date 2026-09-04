<?php

namespace Cubo\Tests\Unit\Validation;

use Cubo\Validation\Sanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sanitizer::class)]
final class SanitizerTest extends TestCase
{
    # ------------------------------------------------------ CONTRATO DE AUSENCIA

    public function testCampoForaDoArrayContinuaFora(): void
    {
        $s = new Sanitizer(['nome' => 'ana'], ['mon_valor' => 'money']);

        $this->assertTrue($s->sanitize());
        $this->assertArrayNotHasKey('mon_valor', $s->getData());
    }

    public function testCampoPresenteEVazioViraNullENaoSomeDoArray(): void
    {
        $s = new Sanitizer(['data_venc' => '', 'mon_valor' => '   '], [
            'data_venc' => 'date',
            'mon_valor' => 'money',
        ]);

        $this->assertTrue($s->sanitize());
        $this->assertArrayHasKey('data_venc', $s->getData());
        $this->assertNull($s->getData()['data_venc']);
        $this->assertNull($s->getData()['mon_valor']);
    }

    public function testCampoNaoDeclaradoAtravessaIntacto(): void
    {
        $s = new Sanitizer(['mon_valor' => '10,50', 'obs' => '  cru  '], ['mon_valor' => 'money']);
        $s->sanitize();

        $this->assertSame('  cru  ', $s->getData()['obs']);
    }

    # ------------------------------------------------------ MOEDA

    #[DataProvider('provedorMoeda')]
    public function testFiltroMoney(string $entrada, ?string $esperado): void
    {
        $s = new Sanitizer(['mon_valor' => $entrada], ['mon_valor' => 'money']);
        $s->sanitize();

        $this->assertSame($esperado, $s->getData()['mon_valor']);
    }

    public static function provedorMoeda(): array
    {
        return [
            'mascara br' => ['R$ 1.234,56', '1234.56'],
            'ponto decimal' => ['29.90', '29.90'],
            'milhar sem centavos' => ['1.234', '1234.00'],
            'formato us' => ['1,234.56', '1234.56'],
            'negativo' => ['-45,9', '-45.90'],
            'vazio' => ['', null],
        ];
    }

    public function testMoneyComCasasCustomizadas(): void
    {
        $s = new Sanitizer(['mon_taxa' => '1,5'], ['mon_taxa' => 'money:4']);
        $s->sanitize();

        $this->assertSame('1.5000', $s->getData()['mon_taxa']);
    }

    /** Texto sem digito nao pode virar 0.00: o usuario digitou algo, so nao era dinheiro. */
    public function testMoneySemDigitoErraEmVezDeViraZero(): void
    {
        $s = new Sanitizer(['mon_valor' => 'abc'], ['mon_valor' => 'money']);

        $this->assertFalse($s->sanitize());
        $this->assertNull($s->getData()['mon_valor']);
        $this->assertSame('mon_valor nao e um valor monetario valido', $s->getErrorsFlat()['mon_valor']);
    }

    # ------------------------------------------------------ DATA

    public function testFiltroDateConverteBrParaIso(): void
    {
        $s = new Sanitizer(['data_venc' => '05/01/2026'], ['data_venc' => 'date']);

        $this->assertTrue($s->sanitize());
        $this->assertSame('2026-01-05', $s->getData()['data_venc']);
    }

    public function testFiltroDateAceitaApelidoDeFormato(): void
    {
        $s = new Sanitizer(['data_venc' => '2026-01-05'], ['data_venc' => 'date:br']);
        $s->sanitize();

        $this->assertSame('05/01/2026', $s->getData()['data_venc']);
    }

    /** 31/02 e sintaxe valida e data inexistente; tem que reprovar, nao transbordar. */
    public function testFiltroDateRecusaDiaInexistente(): void
    {
        $s = new Sanitizer(['data_venc' => '31/02/2026'], ['data_venc' => 'date']);

        $this->assertFalse($s->sanitize());
        $this->assertNull($s->getData()['data_venc']);
        $this->assertSame('data_venc nao e uma data valida', $s->getErrorsFlat()['data_venc']);
    }

    # ------------------------------------------------------ NUMEROS E TEXTO

    public function testFiltroInt(): void
    {
        $s = new Sanitizer(['num_qtd' => ' 42 '], ['num_qtd' => 'int']);

        $this->assertTrue($s->sanitize());
        $this->assertSame(42, $s->getData()['num_qtd']);
    }

    /** Truncar "3.7" para 3 calado perde dado sem avisar ninguem. */
    public function testIntRecusaFracaoEmVezDeTruncar(): void
    {
        $s = new Sanitizer(['num_qtd' => '3.7'], ['num_qtd' => 'int']);

        $this->assertFalse($s->sanitize());
        $this->assertNull($s->getData()['num_qtd']);
    }

    public function testFiltroFloat(): void
    {
        $s = new Sanitizer(['num_peso' => '3.7'], ['num_peso' => 'float']);
        $s->sanitize();

        $this->assertSame(3.7, $s->getData()['num_peso']);
    }

    public function testFiltrosDeTextoDevolvemStringENaoNull(): void
    {
        $s = new Sanitizer(
            ['nome' => '  ana  ', 'uf' => 'sp', 'slug' => 'ANA', 'cpf' => '123.456.789-09', 'vazio' => '   '],
            ['nome' => 'trim', 'uf' => 'upper', 'slug' => 'lower', 'cpf' => 'digits', 'vazio' => 'trim'],
        );

        $this->assertTrue($s->sanitize());
        $this->assertSame('ana', $s->getData()['nome']);
        $this->assertSame('SP', $s->getData()['uf']);
        $this->assertSame('ana', $s->getData()['slug']);
        $this->assertSame('12345678909', $s->getData()['cpf']);
        $this->assertSame('', $s->getData()['vazio']);
    }

    # ------------------------------------------------------ ENCADEAMENTO E ERROS

    public function testFiltrosEncadeiam(): void
    {
        $s = new Sanitizer(['mon_valor' => '  R$ 10,5  '], ['mon_valor' => 'trim|money']);
        $s->sanitize();

        $this->assertSame('10.50', $s->getData()['mon_valor']);
    }

    /** Depois que vira null nao ha o que o filtro seguinte converta. */
    public function testEncadeamentoParaNoPrimeiroNull(): void
    {
        $s = new Sanitizer(['data_venc' => ''], ['data_venc' => 'date|upper']);

        $this->assertTrue($s->sanitize());
        $this->assertNull($s->getData()['data_venc']);
    }

    public function testValorCompostoNaoViraStringNaMarra(): void
    {
        $s = new Sanitizer(['mon_valor' => ['10', '20']], ['mon_valor' => 'money']);

        $this->assertFalse($s->sanitize());
        $this->assertNull($s->getData()['mon_valor']);
    }

    public function testFiltroDesconhecidoEstoura(): void
    {
        $s = new Sanitizer(['nome' => 'ana'], ['nome' => 'inexistente']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filtro de sanitizacao desconhecido');

        $s->sanitize();
    }

    public function testSanitizeDuasVezesNaoAcumulaErro(): void
    {
        $s = new Sanitizer(['num_qtd' => 'x'], ['num_qtd' => 'int']);
        $s->sanitize();
        $s->sanitize();

        $this->assertCount(1, $s->getErrors()['num_qtd']);
    }

    /** O formato flat e o mesmo do Validator, para os dois passos servirem a mesma view. */
    public function testErrosFlatBatemComOContratoDoValidator(): void
    {
        $s = new Sanitizer(['data_venc' => '31/02/2026'], ['data_venc' => 'date']);
        $s->sanitize();

        $this->assertSame(['data_venc' => 'data_venc nao e uma data valida'], $s->getErrorsFlat());
    }
}
