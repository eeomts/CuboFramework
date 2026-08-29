<?php

namespace Cubo\Auth;

/**
 * Chave de API resolvida, no formato que o framework entende.
 *
 * Existe para o framework nao depender do model da app: quem le a tabela e a
 * app, via ApiKeyRepository, e o que atravessa a fronteira e este objeto burro.
 *
 * O segredo NAO viaja em texto: o que a app entrega e o hash, e a comparacao
 * acontece aqui dentro.
 *
 * @package Cubo
 * @author v2: Mateus - github.com/eeomts
 */
final readonly class ApiKey
{
    /**
     * @param int $conta Id da conta dona da chave (ex-fk_conta).
     * @param string $secretHash Hash do app_secret, como password_hash() gerou.
     * @param string|null $urlAccess Host autorizado a consumir a API com esta
     *                               chave (coluna url_access). null, '' ou '%'
     *                               significam "qualquer host".
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
