<?php

namespace Cubo\Storage;

use Cubo\Exceptions\StorageException;

/**
 * Arquivo recem-chegado, ainda no diretorio temporario do PHP.
 *
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final readonly class UploadedFile
{
    public function __construct(
        public string $originalName,
        public string $tempPath,
        public int $size,
    ) {
    }

    /**
     * @throws StorageException se o proprio PHP reportou erro no upload
     */
    public static function fromPhpUpload(array $file): self
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            throw StorageException::phpUploadFailed((int) $error);
        }

        return new self(
            originalName: (string) ($file['name'] ?? ''),
            tempPath: (string) ($file['tmp_name'] ?? ''),
            size: (int) ($file['size'] ?? 0),
        );
    }

    /**
     * Extensao em minusculas e SEM o ponto.
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }
}