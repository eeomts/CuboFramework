<?php

namespace Cubo\Storage;

/**
 * Onde os arquivos ficam guardados.
 *
 * Quem usa conversa com esta interface, nao com o disco. 
 * Dois ganhos: 
 * o teste roda sem tocar em arquivo de verdade, e trocar disco local por S3/NFS nao encosta na
 * regra de negocio.
 *
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