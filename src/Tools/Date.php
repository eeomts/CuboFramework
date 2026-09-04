<?php

/**
 * Datas em formato MySQL (aaaa-mm-dd) e brasileiro (dd/mm/aaaa), com ou sem H:i:s.
 *
 * @package Cubo
 * @author v1: Cristiano
 * @author v2: Mateus - github.com/eeomts
 */

namespace Cubo\Tools;

class Date
{
	
	private const MONTHS = [
		1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
		5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
		9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
	];

	
	private const FIXED_HOLIDAYS = ['01-01', '21-04', '01-05', '07-09', '12-10', '02-11', '15-11', '25-12'];

	/** @param string $formato formato desejado; o padrao e o brasileiro */
	public static function now(string $formato = 'd/m/Y H:i:s'): string
	{
		return (new \DateTimeImmutable())->format($formato);
	}

	/** @param string $type h horas, i minutos, s segundos, d dias, m meses, Y anos */
	public static function addTime(string $data, int $value, string $type): string
	{
		return self::parseDate($data)->add(self::interval($value, $type))->format('d/m/Y H:i:s');
	}

	/** @param string $type h horas, i minutos, s segundos, d dias, m meses, Y anos */
	public static function removeTime(string $data, int $value, string $type): string
	{
		return self::parseDate($data)->sub(self::interval($value, $type))->format('d/m/Y H:i:s');
	}

	/** Recebe a data em qualquer formatacao e devolve no formato pedido. */
	public static function formataData(string $data, string $formato): string
	{
		return self::parseDate($data)->format($formato);
	}


	/** @param string $unit 'Y' anos, 'M' meses, 'D' dias, 'H' horas, 'I' minutos; vazio devolve segundos */
	public static function diff(string $begin, string $end, string $unit = ''): int
	{
		$diff = self::parseDate($end)->getTimestamp() - self::parseDate($begin)->getTimestamp();

		return (int) match ($unit) {
			'Y' => floor($diff / 31536000),
			'M' => floor($diff / 2592000),
			'D' => floor($diff / 86400),
			'H' => floor($diff / 3600),
			'I' => floor($diff / 60),
			default => $diff,
		};
	}

	/**
	 * Apelidos: eng=Y-m-d, engh=Y-m-d H:i:s, br=d/m/Y, brh=d/m/Y H:i:s, bh=H:i, bhs=H:i:s.
	 * Qualquer outro valor vale como formato literal do date().
	 */
	public static function convert(string $date, string $format): string
	{
		$aliases = [
			'eng' => 'Y-m-d', 'engh' => 'Y-m-d H:i:s',
			'br' => 'd/m/Y', 'brh' => 'd/m/Y H:i:s',
			'bh' => 'H:i', 'bhs' => 'H:i:s',
		];

		return self::formataData($date, $aliases[$format] ?? $format);
	}

	/**
	 * O convert sem exception: entrada torta e caso esperado em formulario.
	 */
	public static function tryConvert(string $date, string $format): ?string
	{
		try {
			return self::convert($date, $format);
		} catch (\InvalidArgumentException) {
			return null;
		}
	}

	/** Segundos como duração HH:MM:SS; aceita acima de 24h por ser duração. */
	public static function formatDuration(int $seconds): string
	{
		$hours = intdiv($seconds, 3600);
		$seconds -= $hours * 3600;
		$minutes = intdiv($seconds, 60);
		$seconds -= $minutes * 60;

		return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
	}

	/** Duração H:i:s em total de segundos; segundos ausentes contam como zero. */
	public static function durationToSeconds(string $time): int
	{
		[$h, $m, $s] = array_pad(explode(':', $time), 3, 0);

		return (int) $h * 3600 + (int) $m * 60 + (int) $s;
	}

	/** $return 'hr' devolve HH:MM:SS; qualquer outro valor devolve segundos. */
	public static function diffHours(string $start, string $end, string $return = 'hr'): string|int
	{
		$diff = self::durationToSeconds($end) - self::durationToSeconds($start);

		return $return === 'hr' ? self::formatDuration($diff) : $diff;
	}

	/** Soma de duas durações H:i:s. */
	public static function sumHours(string $start, string $end, string $return = 'hr'): string|int
	{
		$soma = self::durationToSeconds($start) + self::durationToSeconds($end);

		return $return === 'hr' ? self::formatDuration($soma) : $soma;
	}

	/**
	 * $type 'dh' inclui " as HH:MM"; 'd' devolve so a data.
	 *
	 * @example spellDate('2026-01-05 15:30:00') => '05 de janeiro de 2026 as 15:30'
	 */
	public static function spellDate(string $date, string $type = 'dh'): string
	{
		$ts = strtotime($date);

		$extenso = date('d', $ts) . ' de ' . self::MONTHS[(int) date('m', $ts)] . ' de ' . date('Y', $ts);

		return $type === 'dh' ? $extenso . ' as ' . date('H:i', $ts) : $extenso;
	}

	# Nome do mês pelo número (1-12).
	public static function monthName(int $month): string
	{
		return self::MONTHS[$month] ?? '';
	}

	/**
	 * Valida inclusive a existencia real do dia.
	 * $type: en=Y-m-d H:i:s, br=d/m/Y H:i:s, -br=d/m/Y, -en=Y-m-d.
	 */
	public static function isValidFormat(string $date, string $type = 'en'): bool
	{
		$formats = [
			'en' => 'Y-m-d H:i:s', 'br' => 'd/m/Y H:i:s',
			'-br' => 'd/m/Y', '-en' => 'Y-m-d',
		];

		if (!isset($formats[$type])) {
			return false;
		}

		$dt = \DateTimeImmutable::createFromFormat('!' . $formats[$type], $date);

		return $dt !== false && $dt->format($formats[$type]) === $date;
	}

	/** Número de série de data do Excel em timestamp Unix. */
	public static function fromExcel(float $serial): int
	{
		$days = floor($serial);
		$fraction = $serial - $days;

		return (int) (($days > 0 ? ($days - 25569) * 86400 : 0) + $fraction * 86400);
	}

	/**
	 * @param bool $ajuste true devolve o timestamp do proximo dia util; false apenas
	 *                     classifica em 'F' feriado, 'S' sabado, 'D' domingo
	 */
	public static function businessDay(int $timestamp, bool $ajuste = true): int|string
	{
		$original = $timestamp;
		$somaDias = 0;

		$easter = easter_date((int) date('Y', $timestamp));
		$holidays = array_merge(self::FIXED_HOLIDAYS, [
			date('d-m', $easter), // Páscoa
			date('d-m', $easter - 47 * 86400), // Carnaval
			date('d-m', $easter + 60 * 86400), // Corpus Christi
			date('d-m', $easter - 2 * 86400), // Sexta-feira da Paixao
		]);

		if (in_array(date('d-m', $timestamp), $holidays, true)) {
			if (!$ajuste) {
				return 'F';
			}
			$somaDias = (int) date('d', $timestamp) + 1;
			$timestamp = mktime(0, 0, 0, (int) date('m', $timestamp), (int) date('d', $timestamp) + 1, (int) date('Y', $timestamp));
		}

		$weekday = (int) date('w', $timestamp);

		if ($weekday === 0) { // domingo
			if (!$ajuste) {
				return 'D';
			}
			$somaDias = (int) date('d', $timestamp) + 1;
			$timestamp = mktime(0, 0, 0, (int) date('m', $timestamp), (int) date('d', $timestamp) + 1, (int) date('Y', $timestamp));
		} elseif ($weekday === 6) { // sábado
			if (!$ajuste) {
				return 'S';
			}
			$somaDias = (int) date('d', $timestamp) + 2;
			$timestamp = mktime(0, 0, 0, (int) date('m', $timestamp), (int) date('d', $timestamp) + 2, (int) date('Y', $timestamp));
		}

		if ($ajuste) {
			$diasMes = (int) date('t', mktime(0, 0, 0, (int) date('m', $original), 1, (int) date('Y', $original)));

			if ($somaDias > $diasMes) {
				$timestamp = mktime(0, 0, 0, (int) date('m', $original), $diasMes, (int) date('Y', $original));
				$retorno = self::businessDay($timestamp, false);

				if ($retorno === 'F' || $retorno === 'S') {
					$timestamp = mktime(0, 0, 0, (int) date('m', $timestamp), (int) date('d', $timestamp) - 1, (int) date('Y', $timestamp));
				} elseif ($retorno === 'D') {
					$timestamp = mktime(0, 0, 0, (int) date('m', $timestamp), (int) date('d', $timestamp) - 2, (int) date('Y', $timestamp));
				}
			}
		}

		return $timestamp;
	}

	#PRIVATES

	/**
	 * H:i sem segundos e o que o input type=time manda. O `|` zera o que nao foi
	 * lido, senao a hora herda os segundos do relogio.
	 *
	 * @throws \InvalidArgumentException se nenhum formato casar
	 */
	private static function parseDate(string $data): \DateTimeImmutable
	{
		$formatos = [
			'd/m/Y H:i:s', 'd/m/Y H:i|', 'd/m/Y|',
			'Y-m-d H:i:s', 'Y-m-d H:i|', 'Y-m-d|',
		];

		foreach ($formatos as $format) {
			$dat = \DateTimeImmutable::createFromFormat($format, $data);
			$erros = \DateTimeImmutable::getLastErrors();

			# createFromFormat NAO devolve false para data impossivel: transborda
			# (2026-02-30 vira 2026-03-02) e so registra warning. Dai o getLastErrors.
			if ($dat !== false && ($erros === false || $erros['warning_count'] === 0)) {
				return $dat;
			}
		}
		throw new \InvalidArgumentException("Formato de data não reconhecido: {$data}");
	}

	private static function interval(int $value, string $type): \DateInterval
	{
		return match (strtolower($type)) {
			'h' => new \DateInterval("PT{$value}H"),
			'i' => new \DateInterval("PT{$value}M"),
			's' => new \DateInterval("PT{$value}S"),
			'm' => new \DateInterval("P{$value}M"),
			'd' => new \DateInterval("P{$value}D"),
			'y' => new \DateInterval("P{$value}Y"),
			default => throw new \InvalidArgumentException("Tipo inválido: {$type}"),
		};
	}
}
