<?php

namespace Cubo\Tools;

use Cubo\Exceptions\StorageException;
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

    /**
     * Copia uma pasta inteira, criando o destino se preciso.
     *
     * @param list<string> $skip nomes de primeiro nivel a ignorar
     * @throws StorageException se o destino nao puder ser criado ou escrito
     */
    public static function copyDirectory(string $source, string $destination, array $skip = []): void
    {
        if (!is_dir($source)) {
            throw StorageException::for("Pasta de origem nao encontrada: {$source}");
        }

        self::makeDirectory($destination);

        foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $item) {
            /** @var \SplFileInfo $item */
            if (in_array($item->getFilename(), $skip, true)) {
                continue;
            }

            $target = $destination . DIRECTORY_SEPARATOR . $item->getFilename();

            if ($item->isDir()) {
                self::copyDirectory($item->getPathname(), $target);
                continue;
            }

            if (!copy($item->getPathname(), $target)) {
                throw StorageException::for("Falha ao copiar para: {$target}");
            }
        }
    }

    /**
     * Apaga uma pasta e todo o conteudo. Caminho inexistente e no-op.
     */
    public static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }

    /**
     * @throws StorageException
     */
    public static function makeDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw StorageException::for("Nao foi possivel criar a pasta: {$path}");
        }
    }

    /** Pasta inexistente conta como vazia. */
    public static function isEmptyDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return true;
        }

        return !(new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS))->valid();
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
