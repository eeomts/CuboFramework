<?php

namespace Cubo\Http;

use Cubo\Storage\UploadedFile;
use Cubo\Validation\Sanitizer;
use Cubo\Validation\Validator;
use Cubo\Validation\ValidationException;

/**
 * Encapsulacao de $_SERVER, $_GET, $_POST, $_FILES.
 *
 * @package Cubo
 */
class Request
{
    /** Cabecalhos que o PHP entrega sem o prefixo HTTP_ (heranca do CGI). */
    private const CABECALHOS_SEM_PREFIXO = ['CONTENT_TYPE', 'CONTENT_LENGTH'];

    private const VERBOS_SPOOFAVEIS = ['PUT', 'PATCH', 'DELETE'];

    private array $server;
    private array $get;
    private array $post;
    private array $files;

    /** @var array<string, mixed> preenchido pelos middlewares */
    private array $attributes = [];

    /** @param bool $trustProxy sem proxy na frente qualquer cliente forja o X-Forwarded-Proto */
    public function __construct(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?array $files = null,
        private readonly bool $trustProxy = false,
    ) {
        $this->server = $server ?? $_SERVER;
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
    }

    /** Formulario so fala GET e POST: um POST com _method assume PUT, PATCH ou DELETE. */
    public function method(): string
    {
        $metodo = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($metodo === 'POST' && isset($this->post['_method'])) {
            $declarado = strtoupper((string) $this->post['_method']);

            if (in_array($declarado, self::VERBOS_SPOOFAVEIS, true)) {
                return $declarado;
            }
        }

        return $metodo;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }

    public function isDelete(): bool
    {
        return $this->method() === 'DELETE';
    }

    public function isPatch(): bool
    {
        return $this->method() === 'PATCH';
    }

    public function post(string $key = null): mixed
    {
        return $key === null ? $this->post : ($this->post[$key] ?? null);
    }

    public function get(string $key = null): mixed
    {
        return $key === null ? $this->get : ($this->get[$key] ?? null);
    }

    /** POST leva prioridade. */
    public function input(string $key): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? null;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->get[$key]);
    }

    /** @throws \Cubo\Exceptions\StorageException se o PHP reportou erro no upload */
    public function file(string $key): ?UploadedFile
    {
        $entrada = $this->files[$key] ?? null;

        return is_array($entrada) ? UploadedFile::fromPhpUpload($entrada) : null;
    }

    public function header(string $name): ?string
    {
        $chave = strtoupper(str_replace('-', '_', $name));

        # lista fechada: sem ela header('Request-Method') leria REQUEST_METHOD e abriria o $_SERVER inteiro
        if (in_array($chave, self::CABECALHOS_SEM_PREFIXO, true)) {
            return $this->server[$chave] ?? null;
        }

        return $this->server['HTTP_' . $chave] ?? null;
    }

    public function authorization(): ?string
    {
        # Apache com mod_php nao repassa o Authorization; com rewrite ele volta prefixado de REDIRECT_
        return $this->header('Authorization')
            ?? $this->server['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;
    }

    /** Sem query string. */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return (string) parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    public function queryString(): string
    {
        return $this->server['QUERY_STRING'] ?? '';
    }

    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? '';
    }

    /**
     * Atras de proxy que termina TLS o PHP nao recebe HTTPS: o protocolo real so
     * chega no X-Forwarded-Proto, lido apenas com trustProxy ligado.
     */
    public function scheme(): string
    {
        if ($this->trustProxy) {
            $encaminhado = $this->header('X-Forwarded-Proto');

            if ($encaminhado !== null && trim($encaminhado) !== '') {
                # cadeia de proxies manda lista; o primeiro e o do cliente
                return strtolower(trim(explode(',', $encaminhado)[0]));
            }
        }

        if (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return 'https';
        }

        return 'http';
    }

    public function url(): string
    {
        return $this->scheme() . '://' . $this->host() . $this->path();
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Como o middleware entrega dado ao controlador. Devolve copia em vez de
     * mutar: quem ja tem a requisicao em maos nao muda de comportamento depois.
     */
    public function withAttribute(string $name, mixed $value): static
    {
        $copia = clone $this;
        $copia->attributes[$name] = $value;

        return $copia;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Ao contrario do validate(), devolve o array INTEIRO: sanitizar dois campos
     * de dez nao pode sumir com os outros oito.
     *
     * @param array<string, string> $rules chave => 'trim|money'
     * @throws ValidationException se algum campo nao puder ser normalizado
     * @return array<string, mixed> todos os dados, com os declarados normalizados
     */
    public function sanitize(array $rules): array
    {
        $sanitizer = new Sanitizer(array_merge($this->get, $this->post), $rules);

        if (!$sanitizer->sanitize()) {
            throw ValidationException::withErrors($sanitizer->getErrors());
        }

        return $sanitizer->getData();
    }

    /**
     * Nao guarda estado: a requisicao segue imutavel e quem precisa dos dados
     * depois segura o retorno.
     *
     * @param array<string, string> $rules chave => 'required|min:3|max:100'
     * @throws ValidationException se a validacao falhar
     * @return array<string, mixed> apenas os campos declarados nas regras
     */
    public function validate(array $rules): array
    {
        $dados = array_merge($this->get, $this->post);
        $validator = new Validator($dados, $rules);

        if (!$validator->validate()) {
            throw ValidationException::withErrors($validator->getErrors());
        }

        return array_filter(
            $dados,
            fn ($chave) => isset($rules[$chave]),
            ARRAY_FILTER_USE_KEY
        );
    }
}
