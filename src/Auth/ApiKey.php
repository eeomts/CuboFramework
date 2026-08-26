<?php

namespace Cubo\Auth;

/**
 * Chave de API resolvida, no formato que o framework entende.
 *
 * Existe para o framework nao depender do model da app: quem le a tabela e a
 * app, via ApiKeyRepository, e o que atravessa a fronteira e este objeto burro.
 *
 * @package Cubo
 * @author v2: Mateus - github.com/eeomts
 */
final readonly class ApiKey
{
    /**
     * @param int $conta Id da conta dona da chave (ex-fk_conta).
     * @param string|null $urlAccess Host autorizado a consumir a API com esta
     *                               chave (coluna url_access). null, '' ou '%'
     *                               significam "qualquer host".
     */
    public function __construct(
        public int $conta,
        public ?string $urlAccess = null,
    ) {
    }
}
