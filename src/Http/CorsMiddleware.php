<?php

namespace Cubo\Http;

/**
 * Aplica os cabecalhos de CORS como middleware.
 *
 * O Cors calcula, este middleware pluga o calculo no ciclo da requisicao:
 * responde o preflight sem chegar no controlador e carimba a resposta real.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class CorsMiddleware implements Middleware
{
    public function __construct(private readonly Cors $cors)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $origem = $request->header('Origin');

        // preflight nao carrega credencial nem chega no controlador: 204 seco
        if ($request->method() === 'OPTIONS') {
            return $this->carimba(
                (new Response())->status(204),
                $this->cors->preflightHeadersFor($origem, $request->header('Access-Control-Request-Headers'))
            );
        }

        return $this->carimba($next($request), $this->cors->headersFor($origem));
    }

    /** @param array<string, string> $headers */
    private function carimba(Response $resposta, array $headers): Response
    {
        foreach ($headers as $nome => $valor) {
            $resposta->header($nome, $valor);
        }

        return $resposta;
    }
}
