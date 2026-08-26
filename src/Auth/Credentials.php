<?php

namespace Cubo\Auth;

/**
 * Par app_id / app_secret extraido do header Authorization.
 *
 * Header malformado devolve null em vez de warning, e o segredo pode conter ":"
 * -- so o primeiro separa.
 *
 * @package Cubo
 * @author v2: Mateus - github.com/eeomts
 */
final readonly class Credentials
{
    private function __construct(
        public string $appId,
        public string $appSecret,
    ) {
    }

    /**
     * Interpreta o valor do header Authorization ("app_id:app_secret").
     *
     * @return self|null null quando o header falta ou esta malformado -- quem
     *                  chama trata isso como "nao autenticado", sem warning.
     */
    public static function fromHeader(?string $header): ?self
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        // Limite 2: so o PRIMEIRO ":" separa; o resto pertence ao segredo.
        $parts = explode(':', trim($header), 2);

        if (count($parts) !== 2) {
            return null;
        }

        $appId = trim($parts[0]);
        $appSecret = trim($parts[1]);

        if ($appId === '' || $appSecret === '') {
            return null;
        }

        return new self($appId, $appSecret);
    }
}
