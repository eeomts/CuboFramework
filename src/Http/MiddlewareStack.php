<?php

namespace Cubo\Http;

/**
 * Gerencia a execução de uma cadeia de middlewares.
 *
 * @package Cubo
 */
class MiddlewareStack
{
    /** @var list<Middleware|class-string<Middleware>> */
    private array $middlewares = [];

    /**
     * @throws \InvalidArgumentException se a classe nao implementar Middleware
     */
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

    /**
     * @param callable(Request): Response $final o que roda depois da cadeia
     */
    public function execute(Request $request, callable $final): Response
    {
        return $this->next(0, $request, $final);
    }

    /**
     * O indice viaja por parametro, e nao como estado do objeto: assim um
     * middleware que chame next() mais de uma vez repete a cadeia inteira em vez
     * de pular o que sobrou, e o mesmo stack pode ser executado de novo.
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
