<?php

namespace Cubo\Tests\Unit\Http;

use Cubo\Http\Cors;
use Cubo\Http\CorsMiddleware;
use Cubo\Http\MiddlewareStack;
use Cubo\Http\Request;
use Cubo\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorsMiddleware::class)]
final class CorsMiddlewareTest extends TestCase
{
    private function stack(?string $hostPermitido): MiddlewareStack
    {
        return (new MiddlewareStack())->add(new CorsMiddleware(new Cors($hostPermitido)));
    }

    private function requisicao(string $metodo, ?string $origem): Request
    {
        $server = ['REQUEST_METHOD' => $metodo];

        if ($origem !== null) {
            $server['HTTP_ORIGIN'] = $origem;
        }

        return new Request(server: $server);
    }

    public function testCarimbaAOrigemNaRespostaReal(): void
    {
        $resposta = $this->stack('cliente.com')->execute(
            $this->requisicao('GET', 'https://cliente.com'),
            fn () => Response::text('conteudo')
        );

        $this->assertSame('https://cliente.com', $resposta->getHeaders()['Access-Control-Allow-Origin']);
        $this->assertSame('conteudo', $resposta->getBody());
    }

    /** Origem nao autorizada nao ganha o cabecalho; o navegador ja barra a leitura. */
    public function testOrigemNaoAutorizadaNaoRecebeCabecalho(): void
    {
        $resposta = $this->stack('cliente.com')->execute(
            $this->requisicao('GET', 'https://invasor.com'),
            fn () => Response::text('conteudo')
        );

        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $resposta->getHeaders());
    }

    public function testPreflightRespondeSemChegarNoControlador(): void
    {
        $chegou = false;

        $resposta = $this->stack('cliente.com')->execute(
            $this->requisicao('OPTIONS', 'https://cliente.com'),
            function () use (&$chegou) {
                $chegou = true;
                return Response::text('nao deveria rodar');
            }
        );

        $this->assertFalse($chegou);
        $this->assertSame(204, $resposta->getStatus());
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $resposta->getHeaders());
    }

    public function testPreflightRepassaOsCabecalhosPedidos(): void
    {
        $request = new Request(server: [
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://cliente.com',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, Content-Type',
        ]);

        $resposta = $this->stack('cliente.com')->execute($request, fn () => Response::text(''));

        $this->assertSame(
            'Authorization, Content-Type',
            $resposta->getHeaders()['Access-Control-Allow-Headers']
        );
    }

    public function testRequisicaoSemOrigemPassaIntacta(): void
    {
        $resposta = $this->stack('cliente.com')->execute(
            $this->requisicao('GET', null),
            fn () => Response::text('conteudo')
        );

        $this->assertSame('conteudo', $resposta->getBody());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $resposta->getHeaders());
    }
}
