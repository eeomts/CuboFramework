<?php

namespace Cubo\Auth;

use Cubo\Http\Cors;

/**
 * Autenticacao da API por chave (app_id + app_secret no header Authorization).
 *
 * A chave e resolvida ANTES de emitir os cabecalhos de CORS, e o Allow-Origin
 * sai derivado do url_access dela.
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

    private bool $authorized = false;

    private bool $preflight = false;

    private string $message = self::MSG_DEFAULT;

    private int $conta = 0;

    /**
     * @param ApiKeyRepository $keys Quem sabe ler a tabela de chaves (app).
     * @param bool $allowCredentials Ver Cubo\Http\Cors.
     */
    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly bool $allowCredentials = false,
    ) {
    }

    /**
     * Roda a autenticacao e emite os cabecalhos de CORS cabiveis.
     *
     * Headers e $_SERVER entram por parametro em vez de vir de getallheaders(),
     * que nao existe em todo SAPI e impediria o teste.
     *
     * @param array<string, string> $headers Headers da requisicao.
     * @param array<string, mixed> $server Tipicamente $_SERVER.
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

        $key = $this->keys->findActiveByCredentials($credentials->appId, $credentials->appSecret);

        if ($key === null) {
            // Sem ecoar de volta as credenciais enviadas.
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

        // Allow-Origin sai da allowlist da chave. Origem que nao bate recebe
        // array vazio -> nenhum cabecalho -> o navegador bloqueia a leitura.
        $cors->send($cors->headersFor($origin));

        $this->conta = $key->conta;
        $this->authorized = true;
        $this->message = self::MSG_AUTHORIZED;
    }

    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    /**
     * A requisicao era um preflight do navegador?
     *
     * Nesse caso nao houve o que autenticar: os cabecalhos de CORS ja foram
     * emitidos e quem chama deve encerrar a resposta.
     */
    public function isPreflight(): bool
    {
        return $this->preflight;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Id da conta dona da chave. Só é preenchido quando autorizado.
     */
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

        // Sem Authorization no preflight nao ha chave para resolver, logo nao ha
        // url_access. A allowlist e aplicada na requisicao real.
        $cors = new Cors(null, $this->allowCredentials);

        $cors->send($cors->preflightHeadersFor(
            $origin,
            self::header($headers, 'Access-Control-Request-Headers'),
        ));
    }

    /**
     * Confere o Referer contra a allowlist da chave.
     *
     * Ausencia de Referer NEGA o acesso, inclusive quando url_access e '%'.
     * Referer e falsificavel e vale pouco como controle, mas afrouxar aqui
     * concederia acesso que o v1 negava.
     *
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
     * Le um header ignorando a caixa do nome.
     *
     * Nome de header e case-insensitive por especificacao.
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
