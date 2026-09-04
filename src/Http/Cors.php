<?php

namespace Cubo\Http;

/**
 * Monta os cabecalhos de CORS da API. A allowlist chega pronta de fora.
 *
 * @see Cubo\Auth\ApiKeyRepository
 *
 * @package Cubo
 * @author v1: Reginaldo (Cubo_Auth::enableCORS)
 * @author v2: Mateus - github.com/eeomts
 */
final class Cors
{
    private const WILDCARD = '%';

    /** @var list<string> */
    private const METODOS_PADRAO = ['GET', 'POST', 'OPTIONS'];

    /**
     * @param string|null $allowedHost o url_access da chave; null, '' ou '%' liberam qualquer origem
     * @param bool $allowCredentials cookies/sessao; desligado porque esta API autentica por header
     * @param list<string> $allowedMethods verbos anunciados no preflight
     * @throws \InvalidArgumentException credencial com origem aberta entrega resposta autenticada a qualquer site
     */
    public function __construct(
        private readonly ?string $allowedHost = null,
        private readonly bool $allowCredentials = false,
        private readonly array $allowedMethods = self::METODOS_PADRAO,
    ) {
        if ($allowCredentials && $this->allowsAnyOrigin()) {
            throw new \InvalidArgumentException(
                'allowCredentials exige um host declarado: com origem aberta, '
                . 'qualquer site leria resposta autenticada desta API.'
            );
        }
    }

    public function allowsAnyOrigin(): bool
    {
        $host = $this->allowedHost;

        return $host === null || trim($host) === '' || trim($host) === self::WILDCARD;
    }

    /**
     * Casa o host exato OU subdominio dele. O ponto separador e exigido, entao
     * "cliente.com.evil.net" nao passa por "cliente.com".
     */
    public function allows(?string $origin): bool
    {
        if ($this->allowsAnyOrigin()) {
            return true;
        }

        $host = self::hostOf($origin);
        $allowed = self::hostOf($this->allowedHost);

        if ($host === null || $allowed === null) {
            return false;
        }

        return $host === $allowed || str_ends_with($host, '.' . $allowed);
    }

    /**
     * Origem ausente ou nao autorizada devolve array VAZIO: sem Allow-Origin o
     * navegador ja bloqueia a leitura.
     *
     * @return array<string, string>
     */
    public function headersFor(?string $origin): array
    {
        if ($origin === null || trim($origin) === '' || !$this->allows($origin)) {
            return [];
        }

        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Max-Age' => '86400',
            # avisa caches que a resposta muda conforme a origem que pediu
            'Vary' => 'Origin',
        ];

        if ($this->allowCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }

    /**
     * Cabecalhos da resposta ao preflight (OPTIONS), onde o allowedHost costuma
     * vir vazio: preflight nao carrega Authorization.
     *
     * @param string|null $requestHeaders valor de Access-Control-Request-Headers
     * @return array<string, string>
     */
    public function preflightHeadersFor(?string $origin, ?string $requestHeaders = null): array
    {
        $headers = $this->headersFor($origin);

        if ($headers === []) {
            return [];
        }

        $headers['Access-Control-Allow-Methods'] = implode(', ', $this->allowedMethods);

        if ($requestHeaders !== null && trim($requestHeaders) !== '') {
            $headers['Access-Control-Allow-Headers'] = $requestHeaders;
        }

        return $headers;
    }

    /** @param array<string, mixed> $server */
    public static function isPreflight(array $server): bool
    {
        return ($server['REQUEST_METHOD'] ?? null) === 'OPTIONS';
    }

    /**
     * Unico ponto de I/O da classe.
     *
     * @param array<string, string> $headers
     */
    public function send(array $headers): void
    {
        if (headers_sent()) {
            return;
        }

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Host de uma origem ou de um url_access, nas tres formas que aparecem:
     * "https://app.x.com/foo", "//app.x.com" e "app.x.com" cru.
     *
     * A porta e descartada: url_access guarda dominio, nao origem completa.
     */
    private static function hostOf(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        # sem "//" o parse_url leria tudo como path, nao como host
        if (!str_contains($value, '//')) {
            $value = '//' . $value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
