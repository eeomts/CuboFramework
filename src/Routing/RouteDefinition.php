<?php

namespace Cubo\Routing;

/**
 * Uma rota declarada na tabela: caminho, verbo e alvo ditos explicitamente.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class RouteDefinition
{
    private ?string $name = null;

    /** @var list<string> */
    private array $middleware = [];

    /**
     * @param list<string> $verbs verbos HTTP que esta rota atende
     * @param string $path parametros entre chaves ('/clientes/{id}')
     * @param class-string $controller controlador em FQCN
     * @param string $action metodo chamado no controlador
     */
    public function __construct(
        private readonly array $verbs,
        private readonly string $path,
        private readonly string $controller,
        private readonly string $action,
    ) {
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function middleware(string ...$middleware): self
    {
        foreach ($middleware as $um) {
            $this->middleware[] = $um;
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return class-string */
    public function getController(): string
    {
        return $this->controller;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function aceitaVerbo(string $verb): bool
    {
        return in_array(strtoupper($verb), $this->verbs, true);
    }

    /** @return array<string, string>|null null quando o caminho nao casa */
    public function casa(string $path): ?array
    {
        if (!preg_match($this->regex(), trim($path, '/'), $encontrado)) {
            return null;
        }

        # grupo nomeado aparece tambem por indice numerico; so os nomes valem
        return array_filter($encontrado, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param array<string, string|int> $params
     * @throws \InvalidArgumentException se faltar parametro que o caminho exige
     */
    public function url(array $params = []): string
    {
        return preg_replace_callback(
            '~\{(\w+)\}~',
            function (array $m) use ($params): string {
                if (!array_key_exists($m[1], $params)) {
                    throw new \InvalidArgumentException(
                        "A rota '{$this->path}' exige o parametro '{$m[1]}'."
                    );
                }

                return rawurlencode((string) $params[$m[1]]);
            },
            $this->path
        );
    }

    /**
     * O split alterna literal e parametro. Escapar o caminho inteiro de uma vez
     * nao funcionaria: as proprias chaves seriam escapadas.
     */
    private function regex(): string
    {
        $partes = preg_split('~\{(\w+)\}~', trim($this->path, '/'), -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($partes as $i => $parte) {
            $regex .= $i % 2 === 0
                ? preg_quote($parte, '~')
                : '(?P<' . $parte . '>[^/]+)';
        }

        return '~^' . $regex . '$~';
    }
}
