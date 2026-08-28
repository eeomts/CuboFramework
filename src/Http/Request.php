<?php

namespace Cubo\Http;

use Cubo\Storage\UploadedFile;
use Cubo\Validation\Validator;
use Cubo\Validation\ValidationException;

/**
 * Encapsulação de $_SERVER, $_GET, $_POST, $_FILES.
 *
 * @package Cubo
 */
class Request
{
    /** Cabecalhos que o PHP entrega sem o prefixo HTTP_ (heranca do CGI). */
    private const CABECALHOS_SEM_PREFIXO = ['CONTENT_TYPE', 'CONTENT_LENGTH'];

    /** Verbos que um POST pode assumir via _method. */
    private const VERBOS_SPOOFAVEIS = ['PUT', 'PATCH', 'DELETE'];

    private array $server;
    private array $get;
    private array $post;
    private array $files;

    /** @var array<string, mixed> preenchido pelos middlewares */
    private array $attributes = [];

    /**
     * @param bool $trustProxy libera a leitura de X-Forwarded-Proto. Desligado
     *                         por padrao: sem proxy na frente, qualquer cliente
     *                         forja o cabecalho.
     */
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

    /**
     * Verbo HTTP da requisicao.
     *
     * Formulario HTML so fala GET e POST, entao um POST com o campo _method
     * assume PUT, PATCH ou DELETE. So o POST destrava, e so nesses tres verbos.
     */
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

    /** Valor de uma chave em $_POST, ou todo $_POST. */
    public function post(string $key = null): mixed
    {
        return $key === null ? $this->post : ($this->post[$key] ?? null);
    }

    /** Valor de uma chave em $_GET, ou todo $_GET. */
    public function get(string $key = null): mixed
    {
        return $key === null ? $this->get : ($this->get[$key] ?? null);
    }

    /** Busca em $_POST ou $_GET (POST leva prioridade). */
    public function input(string $key): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? null;
    }

    /** Todos os parametros ($_GET + $_POST). */
    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    /** Verifica se uma chave existe em POST ou GET. */
    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->get[$key]);
    }

    /**
     * Arquivo enviado, ja validado.
     *
     * @throws \Cubo\Exceptions\StorageException se o PHP reportou erro no upload
     *         (arquivo grande demais, por exemplo), que antes chegava mudo
     */
    public function file(string $key): ?UploadedFile
    {
        $entrada = $this->files[$key] ?? null;

        return is_array($entrada) ? UploadedFile::fromPhpUpload($entrada) : null;
    }

    /** Header HTTP enviado pelo cliente. */
    public function header(string $name): ?string
    {
        $chave = strtoupper(str_replace('-', '_', $name));

        // lista fechada: sem ela, header('Request-Method') leria REQUEST_METHOD
        // e o metodo viraria porta de entrada para o $_SERVER inteiro
        if (in_array($chave, self::CABECALHOS_SEM_PREFIXO, true)) {
            return $this->server[$chave] ?? null;
        }

        return $this->server['HTTP_' . $chave] ?? null;
    }

    /** Authorization header (ex: "Bearer token" ou "Basic base64"). */
    public function authorization(): ?string
    {
        // Apache com mod_php nao repassa o Authorization; com rewrite ele
        // reaparece prefixado de REDIRECT_
        return $this->header('Authorization')
            ?? $this->server['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;
    }

    /** Caminho da requisicao (sem query string). */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return (string) parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    /** Query string completa. */
    public function queryString(): string
    {
        return $this->server['QUERY_STRING'] ?? '';
    }

    /** Host do cliente (ex: "localhost:8080"). */
    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? '';
    }

    /**
     * Protocolo (http ou https).
     *
     * Atras de um proxy que termina TLS o PHP nao recebe HTTPS, e o protocolo
     * real so chega no X-Forwarded-Proto -- lido apenas com trustProxy ligado.
     */
    public function scheme(): string
    {
        if ($this->trustProxy) {
            $encaminhado = $this->header('X-Forwarded-Proto');

            if ($encaminhado !== null && trim($encaminhado) !== '') {
                // cadeia de proxies manda lista; o primeiro e o do cliente
                return strtolower(trim(explode(',', $encaminhado)[0]));
            }
        }

        if (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return 'https';
        }

        return 'http';
    }

    /** URL completa da requisicao. */
    public function url(): string
    {
        return $this->scheme() . '://' . $this->host() . $this->path();
    }

    /** IP do cliente. */
    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Copia da requisicao com mais um atributo. E como o middleware entrega
     * dado ao controlador (o usuario autenticado, por exemplo).
     *
     * Devolve copia em vez de mutar: quem ja tem a requisicao em maos nao muda
     * de comportamento porque um middleware mexeu nela depois.
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
     * Valida os dados da requisicao e devolve o que passou.
     *
     * Nao guarda estado: a requisicao segue imutavel, e quem precisa dos dados
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
