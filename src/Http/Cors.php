<?php

namespace Cubo\Http;

/**
 * Monta os cabecalhos de CORS da API.
 *
 * CORS e como o servidor diz ao navegador quais origens podem LER suas
 * respostas (enviar o navegador sempre deixa; ler, nao).
 *
 * A allowlist chega pronta de fora: e o host autorizado da chave de API que a
 * app resolveu antes (ver Cubo\Auth\ApiKeyRepository). O Cors nao consulta nada.
 *
 * O calculo e puro -- headersFor() devolve array; send() e o unico I/O.
 *
 * @package Cubo
 * @author v1: Reginaldo (Cubo_Auth::enableCORS)
 * @author v2: Mateus - github.com/eeomts
 */
final class Cors
{
    /** Valor de url_access que significa "qualquer origem". */
    private const WILDCARD = '%';

    /**
     * @param string|null $allowedHost Host permitido (o url_access da chave).
     *                                 null, '' ou '%' liberam qualquer origem.
     * @param bool $allowCredentials Permite ao navegador mandar cookies/sessao
     *                               e ao chamador ler a resposta. Fica desligado
     *                               porque esta API autentica por header.
     */
    public function __construct(
        private readonly ?string $allowedHost = null,
        private readonly bool $allowCredentials = false,
    ) {
    }

    /**
     * A chave nao restringe host? Entao qualquer origem passa.
     */
    public function allowsAnyOrigin(): bool
    {
        $host = $this->allowedHost;

        return $host === null || trim($host) === '' || trim($host) === self::WILDCARD;
    }

    /**
     * A origem informada pode ler as respostas desta API?
     *
     * Casa o host exato OU um subdominio dele. O ponto separador e exigido,
     * entao "cliente.com.evil.net" nao passa por "cliente.com".
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
     * Cabecalhos da resposta a uma requisicao normal (nao-preflight).
     *
     * Origem ausente ou nao autorizada devolve array VAZIO: sem Allow-Origin o
     * navegador ja bloqueia a leitura, que e exatamente o que se quer.
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
            // Avisa caches que a resposta muda conforme a origem que pediu.
            'Vary' => 'Origin',
        ];

        if ($this->allowCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }

    /**
     * Cabecalhos da resposta ao preflight (OPTIONS).
     *
     * O navegador nao manda Authorization no preflight, entao aqui nao ha chave
     * para resolver e o allowedHost costuma vir vazio. A allowlist e aplicada na
     * requisicao real, que e a que carrega a chave.
     *
     * @param string|null $requestHeaders Valor de Access-Control-Request-Headers.
     * @return array<string, string>
     */
    public function preflightHeadersFor(?string $origin, ?string $requestHeaders = null): array
    {
        $headers = $this->headersFor($origin);

        if ($headers === []) {
            return [];
        }

        $headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';

        if ($requestHeaders !== null && trim($requestHeaders) !== '') {
            $headers['Access-Control-Allow-Headers'] = $requestHeaders;
        }

        return $headers;
    }

    /**
     * A requisicao atual e um preflight do navegador?
     *
     * Recebe o $_SERVER por parametro (em vez de ler o superglobal) para poder
     * ser testado sem simular estado global.
     *
     * @param array<string, mixed> $server
     */
    public static function isPreflight(array $server): bool
    {
        return ($server['REQUEST_METHOD'] ?? null) === 'OPTIONS';
    }

    /**
     * Efetivamente emite os cabecalhos. Unico ponto de I/O da classe.
     *
     * @param array<string, string> $headers
     */
    public function send(array $headers): void
    {
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Extrai o host de uma origem ou de um url_access.
     *
     * Aceita as tres formas que aparecem na pratica -- "https://app.x.com/foo",
     * "//app.x.com" e "app.x.com" cru (como o url_access costuma estar gravado).
     *
     * A porta e descartada de proposito: url_access guarda dominio, nao origem
     * completa, entao exigir porta igual reprovaria consumidor legitimo.
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

        // Sem "//" o parse_url leria tudo como path, nao como host.
        if (!str_contains($value, '//')) {
            $value = '//' . $value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Auth::enableCORS() -> Cubo\Http\Cors
 *
 * RENOMEADO
 *   enableCORS -> headersFor / preflightHeadersFor / send
 *
 * NOVOS
 *   allows(?string $origin): bool
 *   allowsAnyOrigin(): bool
 *   isPreflight(array $server): bool
 *
 * MUDOU
 *   Allow-Origin -- so sai se a origem bater no url_access da chave; origem
 *     negada devolve [] e o navegador bloqueia a leitura
 *   Allow-Credentials -- false por padrao
 *   Vary: Origin -- passa a ser emitido
 *   comparacao do host -- host exato ou subdominio, nao mais regex
 *
 * DESCARTADO
 *   exit(0) no preflight X quem chama decide encerrar
 */
