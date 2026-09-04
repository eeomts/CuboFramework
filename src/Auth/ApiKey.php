<?php

namespace Cubo\Auth;

/**
 * Chave de API resolvida, no formato que o framework entende.
 *
 * @package Cubo
 * @author v2: Mateus - github.com/eeomts
 */
final readonly class ApiKey
{
    /**
     * @param int $conta id da conta dona da chave
     * @param string $secretHash hash do app_secret, como password_hash() gerou
     * @param string|null $urlAccess host autorizado; null, '' ou '%' liberam qualquer um
     */
    public function __construct(
        public int $conta,
        public string $secretHash,
        public ?string $urlAccess = null,
    ) {
    }

    public static function hashSecret(string $secret): string
    {
        return password_hash($secret, PASSWORD_DEFAULT);
    }

    public function secretMatches(string $secret): bool
    {
        return password_verify($secret, $this->secretHash);
    }
}
