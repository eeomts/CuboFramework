<?php

namespace Cubo\Storage;

use Cubo\Exceptions\StorageException;
use Cubo\Tools\Filesystem;

/**
 * Arquivos gravados em disco, num diretorio.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class LocalFileStore implements FileStore
{
    public function __construct(private readonly string $directory)
    {
    }

    public function put(UploadedFile $file, string $storedName): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw StorageException::writeFailed($storedName);
        }

        $destination = $this->fullPath($storedName);

        # move_uploaded_file so aceita arquivo vindo de HTTP POST: e o que impede
        # forjar um tempPath apontando para /etc/passwd. O rename e o caminho de
        # teste/CLI, por isso fica no else e nunca como fallback de falha.
        $moved = is_uploaded_file($file->tempPath)
            ? move_uploaded_file($file->tempPath, $destination)
            : rename($file->tempPath, $destination);

        if (!$moved) {
            throw StorageException::writeFailed($storedName);
        }
    }

    public function delete(string $storedName): bool
    {
        $path = $this->fullPath($storedName);

        return is_file($path) && unlink($path);
    }

    public function exists(string $storedName): bool
    {
        return is_file($this->fullPath($storedName));
    }

    public function usedBytes(): int
    {
        return Filesystem::getSizeFolder($this->directory);
    }

    /**
     * basename() e a trava contra path traversal: nome com '../' nao escapa do
     * diretorio.
     */
    public function fullPath(string $storedName): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . basename($storedName);
    }
}
