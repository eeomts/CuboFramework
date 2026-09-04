<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Date;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Date::class)]
final class DateTest extends TestCase
{

    public function testDiffEmDiasEDeterministicoSemHora(): void
    {
        $this->assertSame(1, Date::diff('25/12/2026', '26/12/2026', 'D'));
    }

    public function testDiffSemUnidadeRetornaSegundos(): void
    {
        // 1 dia = 86400 segundos
        $this->assertSame(86400, Date::diff('25/12/2026 00:00:00', '26/12/2026 00:00:00'));
    }

    public function testDiffAceitaFormatoMysql(): void
    {
        $this->assertSame(2, Date::diff('2026-12-25', '2026-12-27', 'D'));
    }

    #[DataProvider('provedorAddTime')]
    public function testAddTime(string $data, int $valor, string $tipo, string $esperado): void
    {
        $this->assertSame($esperado, Date::addTime($data, $valor, $tipo));
    }

    public static function provedorAddTime(): array
    {
        return [
            'soma dias' => ['25/12/2026 00:00:00', 5, 'd', '30/12/2026 00:00:00'],
            'soma horas' => ['25/12/2026 10:00:00', 2, 'h', '25/12/2026 12:00:00'],
            'soma meses' => ['25/11/2026 00:00:00', 1, 'm', '25/12/2026 00:00:00'],
            'vira o ano' => ['31/12/2026 00:00:00', 1, 'd', '01/01/2027 00:00:00'],
        ];
    }

    public function testRemoveTimeSubtraiHoras(): void
    {
        $this->assertSame('25/12/2026 08:00:00', Date::removeTime('25/12/2026 10:00:00', 2, 'h'));
    }

    public function testFormataDataConverteBrParaMysql(): void
    {
        $this->assertSame('2026-12-25', Date::formataData('25/12/2026', 'Y-m-d'));
    }

    public function testFormataDataConverteMysqlParaBr(): void
    {
        $this->assertSame('25/12/2026', Date::formataData('2026-12-25', 'd/m/Y'));
    }

    public function testNowRetornaStringNoFormatoPedido(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', Date::now('Y-m-d'));
    }

    public function testDataInvalidaLancaExcecao(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Date::formataData('data ruim', 'Y-m-d');
    }

    public function testTipoDeIntervaloInvalidoLancaExcecao(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Date::addTime('25/12/2026 00:00:00', 5, 'x');

    }

    /**
     * O formulário manda data e hora em campos separados, e o input type=time
     * devolve "H:i". Essa combinação não casava em nenhum formato e era recusada.
     */
    #[DataProvider('provedorHoraSemSegundos')]
    public function testAceitaHoraSemSegundos(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Date::convert($entrada, 'engh'));
    }

    public static function provedorHoraSemSegundos(): array
    {
        return [
            'br com H:i' => ['01/08/2026 09:30', '2026-08-01 09:30:00'],
            'mysql com H:i' => ['2026-08-01 09:30', '2026-08-01 09:30:00'],
            'br com H:i:s continua' => ['01/08/2026 09:30:45', '2026-08-01 09:30:45'],
            'so data continua' => ['01/08/2026', '2026-08-01 00:00:00'],
        ];
    }

    /** Os segundos vêm zerados, não do relógio -- senão o valor mudaria a cada chamada. */
    public function testHoraSemSegundosNaoHerdaOSegundoAtual(): void
    {
        $this->assertSame('00', Date::convert('01/08/2026 09:30', 's'));
    }

    /** Aceitar H:i não pode ter afrouxado a recusa de lixo no fim da string. */
    #[DataProvider('provedorEntradaRecusada')]
    public function testContinuaRecusandoEntradaQueNaoEData(string $entrada): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Date::convert($entrada, 'engh');
    }

    public static function provedorEntradaRecusada(): array
    {
        return [
            'sobra no fim' => ['2026-08-05 lixo aqui'],
            'texto puro' => ['data ruim'],
            'hora sozinha' => ['09:30'],
        ];
    }

    /**
     * createFromFormat nao devolve false para data impossivel: ele TRANSBORDA e
     * so registra um warning, entao 2026-02-30 virava 2026-03-02 em silencio. O
     * isValidFormat ja recusava; o parseDate por tras do convert/diff/addTime nao.
     */
    #[DataProvider('provedorDataImpossivel')]
    public function testRecusaDataQueNaoExisteEmVezDeTransbordar(string $entrada): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Date::convert($entrada, 'eng');
    }

    public static function provedorDataImpossivel(): array
    {
        return [
            '30 de fevereiro' => ['2026-02-30'],
            'dia 32' => ['32/08/2026'],
            'dia 32 com hora' => ['32/08/2026 09:30'],
            'mes 13' => ['2026-13-01'],
            '29/02 em ano comum' => ['29/02/2026'],
        ];
    }

    /** 29/02 existe em ano bissexto -- a recusa nao pode ser por dia fixo. */
    public function testAceitaVinteNoveDeFevereiroEmAnoBissexto(): void
    {
        $this->assertSame('2024-02-29', Date::convert('29/02/2024', 'eng'));
    }

    # ----------------------------------------------------- REFAC 3 (ex-Tools)

    public function testConvertUsaApelidos(): void
    {
        $this->assertSame('2026-12-25', Date::convert('25/12/2026', 'eng'));
        $this->assertSame('25/12/2026', Date::convert('2026-12-25', 'br'));
        // formato literal quando não é apelido
        $this->assertSame('12/2026', Date::convert('2026-12-25', 'm/Y'));
    }

    public function testFormatDurationEDurationToSecondsSaoInversos(): void
    {
        $this->assertSame('01:01:01', Date::formatDuration(3661));
        $this->assertSame(3661, Date::durationToSeconds('01:01:01'));
        // duração pode passar de 24h
        $this->assertSame('25:00:00', Date::formatDuration(90000));
    }

    public function testDurationToSecondsToleraHoraSemSegundos(): void
    {
        $this->assertSame(3600, Date::durationToSeconds('01:00'));
    }

    public function testDiffHoursESumHours(): void
    {
        $this->assertSame('02:30:00', Date::diffHours('10:00:00', '12:30:00'));
        $this->assertSame('03:30:00', Date::sumHours('01:00:00', '02:30:00'));
        // retorno em segundos quando não é 'hr'
        $this->assertSame(9000, Date::diffHours('10:00:00', '12:30:00', 'seg'));
    }

    public function testSpellDate(): void
    {
        $this->assertSame('05 de janeiro de 2026 as 15:30', Date::spellDate('2026-01-05 15:30:00', 'dh'));
        $this->assertSame('05 de janeiro de 2026', Date::spellDate('2026-01-05', 'd'));
    }

    public function testMonthName(): void
    {
        $this->assertSame('janeiro', Date::monthName(1));
        $this->assertSame('dezembro', Date::monthName(12));
        $this->assertSame('', Date::monthName(99));
    }

    public function testIsValidFormatAceitaValidoERejeitaDataImpossivel(): void
    {
        $this->assertTrue(Date::isValidFormat('2026-01-05 15:30:00', 'en'));
        $this->assertTrue(Date::isValidFormat('05/01/2026', '-br'));
        // 30 de fevereiro: o regex do v1 aceitava; createFromFormat rejeita
        $this->assertFalse(Date::isValidFormat('2026-02-30', '-en'));
        $this->assertFalse(Date::isValidFormat('qualquer', 'en'));
    }

    public function testFromExcel(): void
    {
        // serial 44197 = 2021-01-01 (UTC)
        $this->assertSame(1609459200, Date::fromExcel(44197));
    }

    #[DataProvider('provedorBusinessDay')]
    public function testBusinessDayAjustaParaProximoDiaUtil(string $data, string $esperado): void
    {
        $ajustado = Date::businessDay(self::toTimestamp($data), true);

        $this->assertSame($esperado, date('Y-m-d', $ajustado));
    }

    /** Converte 'Y-m-d' em timestamp (à meia-noite local). */
    private static function toTimestamp(string $data): int
    {
        [$ano, $mes, $dia] = array_map('intval', explode('-', $data));

        return mktime(0, 0, 0, $mes, $dia, $ano);
    }

    public static function provedorBusinessDay(): array
    {
        // golden conferidos com o legado (verificaDataFDSF)
        return [
            'feriado ano novo (qui)' => ['2026-01-01', '2026-01-02'],
            'sabado' => ['2026-01-03', '2026-01-05'],
            'domingo' => ['2026-01-04', '2026-01-05'],
            'segunda normal' => ['2026-01-05', '2026-01-05'],
        ];
    }

    public function testBusinessDaySemAjusteClassifica(): void
    {
        $this->assertSame('F', Date::businessDay(self::toTimestamp('2026-01-01'), false)); // feriado
        $this->assertSame('S', Date::businessDay(self::toTimestamp('2026-01-03'), false)); // sabado
        $this->assertSame('D', Date::businessDay(self::toTimestamp('2026-01-04'), false)); // domingo
        $this->assertIsInt(Date::businessDay(self::toTimestamp('2026-01-05'), false));     // dia util
    }

    # ----------------------------------------------------------- TRY CONVERT

    public function testTryConvertDevolveAConversaoQuandoADataCasa(): void
    {
        $this->assertSame('2026-01-05', Date::tryConvert('05/01/2026', 'eng'));
    }

    /** O convert estoura; este devolve null para quem trata formulario em laco. */
    public function testTryConvertDevolveNullEmVezDeEstourar(): void
    {
        $this->assertNull(Date::tryConvert('bagunca', 'eng'));
    }

    public function testTryConvertRecusaDiaInexistente(): void
    {
        $this->assertNull(Date::tryConvert('31/02/2026', 'eng'));
    }

    public function testTryConvertNaoEngoleOutrosErros(): void
    {
        $this->assertSame('05/01/2026', Date::tryConvert('2026-01-05', 'br'));
    }
}
