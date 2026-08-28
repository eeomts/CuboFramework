<?php

namespace Cubo\Storage;

use Cubo\Exceptions\StorageException;

/**
 * Onde os arquivos ficam guardados.
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
interface FileStore
{
    /**
     * @throws StorageException Se nao conseguir gravar.
     */
    public function put(UploadedFile $file, string $storedName): void;

    /** @return bool false se o arquivo nao existia ou nao pode ser removido. */
    public function delete(string $storedName): bool;

    public function exists(string $storedName): bool;

    public function usedBytes(): int;
}