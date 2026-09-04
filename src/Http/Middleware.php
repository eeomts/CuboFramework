<?php

namespace Cubo\Http;

/**
 * Interface para middlewares HTTP: intercepta, rejeita ou passa adiante.
 *
 * @package Cubo
 */
interface Middleware
{
    /** @param \Closure(Request): Response $next proximo middleware da cadeia */
    public function handle(Request $request, \Closure $next): Response;
}
