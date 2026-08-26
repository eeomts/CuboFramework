<?php

namespace Cubo\Tools;

/**
 * Validação e formatação de documentos brasileiros (CPF e CNPJ).
 *
 * Quarta parte da explosão da God Class Cubo_Tools.
 * O v1 juntava CPF e CNPJ num único validaCPFCNPJ($s, $tipopessoa);
 * agora aqui cada documento tem seu método. 
 * Além disso o ramo de CNPJ do v1 QUEBRAVA no PHP 8 (TypeError: soma
 * iniciada como string ""); foi reescrito com o algoritmo padrão. 
 * Veja o GUIA DE MIGRACAO no fim do arquivo.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Docs
{
    /**
     * Valida um CPF (aceita com ou sem máscara). (ex-validaCPFCNPJ tipo 1)
     */
    public static function isCpf(string $cpf): bool
    {
        $cpf = self::onlyDigits($cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            if ((int) $cpf[$t] !== self::checkDigit($soma)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida um CNPJ (aceita com ou sem máscara). (ex-validaCPFCNPJ tipo 2)
     */
    public static function isCnpj(string $cnpj): bool
    {
        $cnpj = self::onlyDigits($cnpj);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([$pesos1, $pesos2] as $indice => $pesos) {
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += (int) $cnpj[$i] * $peso;
            }
            if ((int) $cnpj[12 + $indice] !== self::checkDigit($soma)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida CPF ou CNPJ detectando pelo número de dígitos.
     */
    public static function isValid(string $doc): bool
    {
        return match (strlen(self::onlyDigits($doc))) {
            11 => self::isCpf($doc),
            14 => self::isCnpj($doc),
            default => false,
        };
    }

    /**
     * Formata um documento pela quantidade de dígitos. (ex-formatarCpfCnpj)
     * CPF: 000.000.000-00  |  CNPJ: 00.000.000/0000-00
     * Documentos com outro tamanho voltam só com os dígitos.
     */
    public static function format(string $doc): string
    {
        $doc = self::onlyDigits($doc);

        return match (strlen($doc)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc),
            default => $doc,
        };
    }

    # ------------------------------------------------------------------- PRIVATE

    private static function onlyDigits(string $doc): string
    {
        return preg_replace('/\D/', '', $doc);
    }

    private static function checkDigit(int $soma): int
    {
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Tools (docs) -> Cubo\Tools\Docs
 *
 * SEPARADOS
 *   validaCPFCNPJ($s, 1) -> isCpf
 *   validaCPFCNPJ($s, 2) -> isCnpj
 *   formatarCpfCnpj -> format
 *
 * NOVOS
 *   isValid -- detecta CPF ou CNPJ pelo tamanho
 *
 * MOVIDOS
 *   geraPass -> Cubo\Security::randomPassword
 */
