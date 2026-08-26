<?php

namespace Cubo\Tools;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Utilitários de sistema de arquivos.
 *
 * Parte da explosão da God Class Cubo_Tools. Reúne o que sobrou de IO em disco
 * que não cabia nas classes de string/número. A formatação de bytes em texto
 * legível (ex-getFormatSize) já vive em Number::formatBytes.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Filesystem
{
    /**
     * Consumo em disco (em bytes) de um diretório, somando recursivamente todos
     * os arquivos. (ex-getSizeFolder)
     *
     * O v1 recursava com $this->getSizeFolder() dentro de um método static, o
     * que e fatal no PHP 8. Aqui a varredura usa RecursiveIteratorIterator, sem
     * recursao manual.
     *
     * @param string $path Caminho do diretório.
     * @return int Total em bytes; 0 se o caminho não existir ou não for pasta.
     */
    public static function getSizeFolder(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $total = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total += $file->getSize();
            }
        }

        return $total;
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Tools (filesystem) -> Cubo\Tools\Filesystem
 *
 * MUDOU
 *   getSizeFolder -- retorno tipado int; pasta inexistente devolve 0
 *
 * MOVIDOS
 *   getFormatSize -> Cubo\Tools\Number::formatBytes
 *   prepareSearch -> Cubo\Database\Search\SearchCriteria
 *
 * DESCARTADOS
 *   readyXlsx X sem substituto
 */
