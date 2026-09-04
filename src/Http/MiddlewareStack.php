<?php

namespace Cubo\Http;

/**
 * Gerencia a execucao de uma cadeia de middlewares.
 *
 * @package Cubo
 */
class MiddlewareStack
{
    /** @var list<Middleware|class-string<Middleware>> */
    private array $middlewares = [];

    /** @throws \InvalidArgumentException se a classe nao implementar Middleware */
    public function add(Middleware|string $middleware): self
    {
        if (is_string($middleware) && !is_subclass_of($middleware, Middleware::class)) {
            throw new \InvalidArgumentException(
                "Middleware invalido: '{$middleware}' nao existe ou nao implementa "
                . Middleware::class . '.'
            );
        }

        $this->middlewares[] = $middleware;

        return $this;
    }

    /** @return list<Middleware|class-string<Middleware>> */
    public function all(): array
    {
        return $this->middlewares;
    }

    /** @param callable(Request): Response $final o que roda depois da cadeia */
    public function execute(Request $request, callable $final): Response
    {
        return $this->next(0, $request, $final);
    }

    /**
     * O indice viaja por parametro, nao como estado do objeto: middleware que
     * chame next() duas vezes repete a cadeia em vez de pular o que sobrou.
     */
    private function next(int $indice, Request $request, callable $final): Response
    {
        if ($indice >= count($this->middlewares)) {
            return $final($request);
        }

        $middleware = $this->middlewares[$indice];

        if (is_string($middleware)) {
            $middleware = new $middleware();
        }

        return $middleware->handle(
            $request,
            fn (Request $req): Response => $this->next($indice + 1, $req, $final),
        );
    }
}
