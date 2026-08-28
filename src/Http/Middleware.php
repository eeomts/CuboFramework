<?php

namespace Cubo\Http;

/**
 * Interface para middlewares HTTP.
 *
 * Middleware intercepta requisição, pode rejeitar ou passa adiante.
 *
 * @package Cubo
 */
interface Middleware
{
    /**
     * Processa a requisição.
     *
     * @param \Closure(Request): Response $next próximo middleware da cadeia
     * @return Response resposta (pode ser do middleware ou do próximo)
     */
    public function handle(Request $request, \Closure $next): Response;
}
