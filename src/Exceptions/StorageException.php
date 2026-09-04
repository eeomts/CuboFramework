<?php

namespace Cubo\Exceptions;

/**
 * Falhas de armazenamento de arquivo.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
class StorageException extends CuboException
{
    public const CODE_WRITE_FAILED = 109;
    public const CODE_UPLOAD_FAILED = 110;

    public static function for(string $message): self
    {
        return new self($message, self::CODE_WRITE_FAILED);
    }

    public static function writeFailed(string $storedName): self
    {
        return new self("Falha ao gravar o arquivo '{$storedName}'.", self::CODE_WRITE_FAILED);
    }

    /** Traduz o codigo de erro que o proprio PHP */
    public static function phpUploadFailed(int $phpErrorCode): self
    {
        $reasons = [
            UPLOAD_ERR_INI_SIZE => 'o arquivo excede o limite do servidor',
            UPLOAD_ERR_FORM_SIZE => 'o arquivo excede o limite do formulario',
            UPLOAD_ERR_PARTIAL => 'o envio foi interrompido',
            UPLOAD_ERR_NO_FILE => 'nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'o servidor esta sem diretorio temporario',
            UPLOAD_ERR_CANT_WRITE => 'o servidor nao conseguiu gravar em disco',
            UPLOAD_ERR_EXTENSION => 'uma extensao do PHP bloqueou o envio',
        ];

        $reason = $reasons[$phpErrorCode] ?? "erro desconhecido ({$phpErrorCode})";

        return new self("Falha no upload: {$reason}.", self::CODE_UPLOAD_FAILED);
    }
}