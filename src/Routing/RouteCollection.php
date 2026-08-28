<?php

namespace Cubo\Routing;

/**
 * Tabela de rotas declaradas.
 *
 * Existe para o que a convencao nao alcanca: verbo HTTP, URL que nao espelha
 * nome de classe, middleware por rota e URL gerada por nome. O que nao estiver
 * declarado aqui continua caindo na convencao do Router.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
class RouteCollection
{
    /** @var list<RouteDefinition> */
    private array $rotas = [];

    /** @param class-string $controller */
    public function get(string $path, string $controller, string $action): RouteDefinition
    {
        return $this->add(['GET'], $path, $controller, $action);
    }

    /** @param class-string $controller */
    public function post(string $path, string $controller, string $action): RouteDefinition
    {
        return $this->add(['POST'], $path, $controller, $action);
    }

    /** @param class-string $controller */
    public function put(string $path, string $controller, string $action): RouteDefinition
    {
        return $this->add(['PUT'], $path, $controller, $action);
    }

    /** @param class-string $controller */
    public function patch(string $path, string $controller, string $action): RouteDefinition
    {
        return $this->add(['PATCH'], $path, $controller, $action);
    }

    /** @param class-string $controller */
    public function delete(string $path, string $controller, string $action): RouteDefinition
    {
        return $this->add(['DELETE'], $path, $controller, $action);
    }

    /**
     * @param list<string> $verbs
     * @param class-string $controller
     */
    public function add(array $verbs, string $path, string $controller, string $action): RouteDefinition
    {
        $rota = new RouteDefinition(
            array_map('strtoupper', $verbs),
            $path,
            $controller,
            $action
        );

        $this->rotas[] = $rota;

        return $rota;
    }

    /**
     * Primeira rota declarada que casa caminho E verbo.
     *
     * A ordem de declaracao decide: a primeira que casar vence, entao rota mais
     * especifica vem antes da mais generica.
     *
     * @param string $path caminho ja relativo a subpasta da app
     * @return Route|null null quando nada casa, e o Router cai na convencao
     */
    public function match(string $path, string $verbo): ?Route
    {
        foreach ($this->rotas as $rota) {
            if (!$rota->aceitaVerbo($verbo)) {
                continue;
            }

            $params = $rota->casa($path);

            if ($params === null) {
                continue;
            }

            return new Route(
                controller: $rota->getController(),
                method: $rota->getAction(),
                params: $params,
                rawParams: array_keys($params),
                module: null,
                controllerClass: $rota->getController(),
                middleware: $rota->getMiddleware(),
                name: $rota->getName(),
            );
        }

        return null;
    }

    /** @return list<RouteDefinition> */
    public function all(): array
    {
        return $this->rotas;
    }

    public function porNome(string $name): ?RouteDefinition
    {
        foreach ($this->rotas as $rota) {
            if ($rota->getName() === $name) {
                return $rota;
            }
        }

        return null;
    }

    /**
     * URL de uma rota nomeada.
     *
     * @param array<string, string|int> $params
     * @throws \InvalidArgumentException se a rota nao existir
     */
    public function url(string $name, array $params = []): string
    {
        $rota = $this->porNome($name);

        if ($rota === null) {
            throw new \InvalidArgumentException("Rota nomeada '{$name}' nao existe.");
        }

        return $rota->url($params);
    }
}
