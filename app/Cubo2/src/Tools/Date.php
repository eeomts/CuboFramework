<?php

/**
 * Responsável por cuidar das datas, contém métodos para identificar e alterar a data enviada.
 * Esta classe trabalha com base em datas no formato mysql aaaa-mm-dd e no formato brasileiro dd/mm/aaaa podendo
 * conter o timestamp completo com H:i:s
 * 
 * @package Cubo
 * @author Cristiano
 * 
 * updated by:
 * @author mateus - github.com/eeomts
 * @package Cubo
 * @updated at 14/06/26 19:19
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

	/**
	 * @param string $formato - formato desejado para a data, padrao brasil
	 * @return string 
	 */
	public static function now(string $formato = 'd/m/Y H:i:s'): string
	{
		return (new \DateTimeImmutable())->format($formato);
	}

	/**
	 * 
	 * Adiciona valor a algum campo da data
	 * Esta função pode ser utilizada para adicionar dias meses, anos...
	 * 
	 * @param integer $value a quantidade do que será adicionada
	 * @param string $type determina o que será adicionada, pode ser:
	 * "h" para horas
	 * "i" para minutos
	 * "s" para segundos
	 * "m" para meses
	 * "d" para dias
	 * "Y" para anos
	 * 
	 * @return string 
	 */
	public static function addTime(string $data, int $value, string $type): string
	{
		return self::parseDate($data)->add(self::interval($value, $type))->format('d/m/Y H:i:s');
	}

	/**
	 * 
	 * Remove valor a algum campo da data
	 * Esta função pode ser utilizada para remover dias meses, anos...
	 * 
	 * @param integer $value a quantidade do que será removida
	 * @param string $type determina o que será removida, pode ser:
	 * "h" para horas
	 * "i" para minutos
	 * "s" para segundos
	 * "m" para meses
	 * "d" para dias
	 * "Y" para anos
	 * 
	 * @return string
	 */
	public static function removeTime(string $data, int $value, string $type): string
	{
		return self::parseDate($data)->sub(self::interval($value, $type))->format('d/m/Y H:i:s');
	}

	/**
	 * Funcao para simplificar a vida
	 * 
	 * Recebe a data com qualquer formatação e retorna a mesma data no formato especificado
	 * 
	 * @param string $formato
	 * @param string $data
	 */
	public static function formataData(string $data, string $formato): string
	{
		return self::parseDate($data)->format($formato);
	}


	/**
	 * Retorna a diferença entre duas datas.
	 *
	 * @param string $begin Data inicial em formato BR ou MySQL
	 * @param string $end   Data final em formato BR ou MySQL
	 * @param string $unit  Unidade de retorno: 'Y' anos, 'M' meses, 'D' dias, 'H' horas, 'I' minutos. Vazio retorna segundos.
	 * @return int
	 */
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
	 * Atalho de formatação com apelidos. (ex-convertData)
	 * Apelidos: eng=Y-m-d, engh=Y-m-d H:i:s, br=d/m/Y, brh=d/m/Y H:i:s, bh=H:i, bhs=H:i:s.
	 * Qualquer outro valor é tratado como formato literal do date().
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
	 * Formata uma quantidade de segundos como duração HH:MM:SS. (ex-getHour)
	 * Aceita horas acima de 24 (é duração, não hora do dia).
	 */
	public static function formatDuration(int $seconds): string
	{
		$hours = intdiv($seconds, 3600);
		$seconds -= $hours * 3600;
		$minutes = intdiv($seconds, 60);
		$seconds -= $minutes * 60;

		return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
	}

	/**
	 * Converte uma duração H:i:s em total de segundos. (ex-getTimestampHour)
	 * Segundos ausentes são tratados como zero.
	 */
	public static function durationToSeconds(string $time): int
	{
		[$h, $m, $s] = array_pad(explode(':', $time), 3, 0);

		return (int) $h * 3600 + (int) $m * 60 + (int) $s;
	}

	/**
	 * Diferença entre duas durações H:i:s. (ex-getDiffHour)
	 * $return 'hr' devolve HH:MM:SS; qualquer outro valor devolve segundos (int).
	 */
	public static function diffHours(string $start, string $end, string $return = 'hr'): string|int
	{
		$diff = self::durationToSeconds($end) - self::durationToSeconds($start);

		return $return === 'hr' ? self::formatDuration($diff) : $diff;
	}

	/**
	 * Soma de duas durações H:i:s. (ex-getSomaHour)
	 */
	public static function sumHours(string $start, string $end, string $return = 'hr'): string|int
	{
		$soma = self::durationToSeconds($start) + self::durationToSeconds($end);

		return $return === 'hr' ? self::formatDuration($soma) : $soma;
	}

	/**
	 * Data por extenso. (ex-extensoData)
	 * $type 'dh' inclui " as HH:MM"; 'd' retorna só a data.
	 *
	 * @example spellDate('2026-01-05 15:30:00') => '05 de janeiro de 2026 as 15:30'
	 */
	public static function spellDate(string $date, string $type = 'dh'): string
	{
		$ts = strtotime($date);

		$extenso = date('d', $ts) . ' de ' . self::MONTHS[(int) date('m', $ts)] . ' de ' . date('Y', $ts);

		return $type === 'dh' ? $extenso . ' as ' . date('H:i', $ts) : $extenso;
	}

	# Nome do mês pelo número (1-12). (ex-extensoMes) 
	public static function monthName(int $month): string
	{
		return self::MONTHS[$month] ?? '';
	}

	/**
	 * Valida se uma string de data bate com um formato conhecido. (ex-validaData)
	 * $type: en=Y-m-d H:i:s, br=d/m/Y H:i:s, -br=d/m/Y, -en=Y-m-d.
	 *
	 * Melhoria: usa createFromFormat, que valida também a data real (o regex do
	 * v1 aceitava dias impossíveis como 2026-02-30). Retorna bool (v1 dava 1/0).
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

	/**
	 * Converte um número de série de data do Excel em timestamp Unix. (ex-convertExcelDate)
	 */
	public static function fromExcel(float $serial): int
	{
		$days = floor($serial);
		$fraction = $serial - $days;

		return (int) (($days > 0 ? ($days - 25569) * 86400 : 0) + $fraction * 86400);
	}

	/**
	 * Ajusta um timestamp para o próximo dia útil (feriados nacionais, sábado e
	 * domingo). (ex-verificaDataFDSF)
	 *
	 * @param bool $ajuste Quando true (padrão) devolve o timestamp do próximo dia
	 *   útil. Quando false, apenas classifica: 'F' feriado, 'S' sábado, 'D'
	 *   domingo, ou o próprio timestamp se já for dia útil.
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
			date('d-m', $easter - 2 * 86400), 
			// Sexta-feira da Paixão
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
	 * parseDate
	 * Parseia uma string de data para DateTimeImmutable.
	 * Tenta os formatos BR e MySQL, com hora completa, com hora sem segundos e sem hora.
	 *
	 * A hora SEM segundos existe porque é o que um formulário manda: o campo de
	 * data e o de hora são separados, e o input type=time devolve "H:i". Antes
	 * essa combinação não casava em nenhum dos formatos e a data era recusada.
	 *
	 * O `|` zera o que não foi lido, então "01/08/2026 09:30" vira 09:30:00 e não
	 * herda os segundos do relógio. Sobra no fim da string continua sendo recusada
	 * pelo próprio createFromFormat.
	 *
	 * @param string $data
	 * @return \DateTimeImmutable
	 * @throws \InvalidArgumentException Se o formato não estiver no array
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

			// createFromFormat NAO devolve false para data impossível: ele
			// transborda (2026-02-30 vira 2026-03-02) e só registra um warning.
			// Sem conferir getLastErrors o parse aceitava a data inexistente e
			// devolvia outra, calada. O isValidFormat desta mesma classe já
			// recusava esse caso; aqui faltava.
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

/*
 * GUIA DE MIGRACAO - Cubo_Tools (datas) -> Cubo\Tools\Date
 *
 * RENOMEADOS
 *   convertData -> convert
 *   getHour -> formatDuration
 *   getTimestampHour -> durationToSeconds
 *   getDiffHour -> diffHours
 *   getSomaHour -> sumHours
 *   extensoData -> spellDate
 *   extensoMes -> monthName
 *   verificaDataFDSF -> businessDay
 *   convertExcelDate -> fromExcel
 */
