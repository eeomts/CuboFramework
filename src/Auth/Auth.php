<?php

namespace Cubo\Auth;

use Cubo\Http\Cors;

/**
 * Autenticacao da API por chave (app_id + app_secret no header Authorization).
 *
 * @package Cubo
 * @author v1: Reginaldo (Cubo_Auth)
 * @author v2: Mateus - github.com/eeomts
 */
final class Auth
{
    private const MSG_DEFAULT = 'No connection. Check your connection.';
    private const MSG_MISSING_AUTHORIZATION = 'API Authorization is necessary. Send your APP ID and APP SECRET for authenticate.';
    private const MSG_BAD_CREDENTIALS = 'No connection. Check your credentials.';
    private const MSG_AUTHORIZED = 'Authorized Connection.';
    private const MSG_PREFLIGHT = 'CORS preflight.';

    private const HASH_DESCARTAVEL = '$2y$10$eFDrd4BUVMJm6E77xuih1ugC3tPbWU8Pjpir4D3BQcazlg5NLlv1G';

    private bool $authorized = false;

    private bool $preflight = false;

    private string $message = self::MSG_DEFAULT;

    private int $conta = 0;

    /**
     * @param ApiKeyRepository $keys quem sabe ler a tabela de chaves
     * @param bool $allowCredentials @see Cubo\Http\Cors
     */
    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly bool $allowCredentials = false,
    ) {
    }

    /**
     * Roda a autenticacao e emite os cabecalhos de CORS cabiveis.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed> $server tipicamente $_SERVER
     */
    public function authenticate(array $headers, array $server): void
    {
        $origin = self::header($headers, 'Origin') ?? self::stringOrNull($server['HTTP_ORIGIN'] ?? null);

        if (Cors::isPreflight($server)) {
            $this->handlePreflight($origin, $headers);

            return;
        }

        $credentials = Credentials::fromHeader(self::header($headers, 'Authorization'));

        if ($credentials === null) {
            $this->message = self::MSG_MISSING_AUTHORIZATION;

            return;
        }

        $key = $this->keys->findActiveByAppId($credentials->appId);

        if (!self::secretConfere($key, $credentials->appSecret)) {
            $this->message = self::MSG_BAD_CREDENTIALS;

            return;
        }

        $cors = new Cors($key->urlAccess, $this->allowCredentials);

        if (!self::refererAllowed($headers, $cors)) {
            $referer = self::header($headers, 'Referer');
            $this->message = 'API no authorization for Your url'
                . ($referer !== null ? ' ' . $referer : '')
                . '. Check with suporte for more details.';

            return;
        }

        # origem fora da allowlist recebe array vazio: sem cabecalho, o navegador bloqueia
        $cors->send($cors->headersFor($origin));

        $this->conta = $key->conta;
        $this->authorized = true;
        $this->message = self::MSG_AUTHORIZED;
    }

    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    /** Preflight nao autentica nada: os cabecalhos ja sairam e quem chama encerra a resposta. */
    public function isPreflight(): bool
    {
        return $this->preflight;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /** Id da conta dona da chave. So e preenchido quando autorizado. */
    public function getConta(): int
    {
        return $this->conta;
    }

    /**
     * @param array<string, string> $headers
     */
    private function handlePreflight(?string $origin, array $headers): void
    {
        $this->preflight = true;
        $this->message = self::MSG_PREFLIGHT;

        # preflight nao traz Authorization, logo nao ha url_access; a allowlist vale na requisicao real
        $cors = new Cors(null, $this->allowCredentials);

        $cors->send($cors->preflightHeadersFor(
            $origin,
            self::header($headers, 'Access-Control-Request-Headers'),
        ));
    }

    private static function secretConfere(?ApiKey $key, string $secret): bool
    {
        if ($key === null) {
            # verifica contra um hash de mentira so para gastar o mesmo tempo do caminho valido
            password_verify($secret, self::HASH_DESCARTAVEL);

            return false;
        }

        return $key->secretMatches($secret);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function refererAllowed(array $headers, Cors $cors): bool
    {
        $referer = self::header($headers, 'Referer');

        if ($referer === null || trim($referer) === '') {
            return false;
        }

        return $cors->allows($referer);
    }

    /**
     * Le um header ignorando a caixa: nome de header e case-insensitive por especificacao.
     *
     * @param array<string, string> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $name = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return self::stringOrNull($value);
            }
        }

        return null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
