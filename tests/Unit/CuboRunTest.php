<?php

namespace Cubo\Tests\Unit;

use Cubo\Cubo;
use Cubo\Http\Middleware;
use Cubo\Http\Request;
use Cubo\Http\Response;
use Cubo\Tests\Support\Controllers\EchoController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cubo::class)]
final class CuboRunTest extends TestCase
{
    private const APP = __DIR__ . '/../Support/app';

    private function prepararRequisicao(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/blog/echo/index';
    }

    /**
     * O corpo que o controlador escreve na saida precisa virar corpo da Response,
     * senao os cabecalhos sairiam depois do corpo e o middleware nao teria o que
     * inspecionar.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCorpoRenderizadoChegaAoMiddlewareDentroDaResponse(): void
    {
        $this->prepararRequisicao();

        $capturada = null;
        $espiao = new class($capturada) implements Middleware {
            public function __construct(private mixed &$capturada)
            {
            }

            public function handle(Request $request, \Closure $next): Response
            {
                $this->capturada = $next($request);

                return $this->capturada;
            }
        };

        ob_start();
        (new Cubo(self::APP))->middleware($espiao)->run();
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $capturada);
        $this->assertStringContainsString(EchoController::MARCADOR, $capturada->getBody());
    }

    /** O corpo capturado tem de ser emitido uma vez so, nao duplicado. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCorpoEhEmitidoUmaUnicaVez(): void
    {
        $this->prepararRequisicao();

        ob_start();
        (new Cubo(self::APP))->run();
        $saida = (string) ob_get_clean();

        $this->assertSame(1, substr_count($saida, EchoController::MARCADOR));
    }

    /** Com o corpo na Response, o middleware consegue reescrever a saida. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMiddlewarePodeReescreverOCorpoRenderizado(): void
    {
        $this->prepararRequisicao();

        $reescreve = new class implements Middleware {
            public function handle(Request $request, \Closure $next): Response
            {
                $resposta = $next($request);

                return $resposta->body(strtoupper($resposta->getBody()));
            }
        };

        ob_start();
        (new Cubo(self::APP))->middleware($reescreve)->run();
        $saida = (string) ob_get_clean();

        $this->assertStringContainsString(strtoupper(EchoController::MARCADOR), $saida);
    }

    /**
     * O ponto do middleware nao e so barrar: ele precisa conseguir entregar dado
     * ao controlador, que e o caso de um AuthMiddleware resolvendo o usuario.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMiddlewarePodeEnriquecerARequestParaOControlador(): void
    {
        $this->prepararRequisicao();

        $autentica = new class implements Middleware {
            public function handle(Request $request, \Closure $next): Response
            {
                return $next($request->withAttribute('usuario', 'joao'));
            }
        };

        ob_start();
        (new Cubo(self::APP))->middleware($autentica)->run();
        $saida = (string) ob_get_clean();

        $this->assertStringContainsString('<i>joao</i>', $saida);
    }

    /** Sem middleware nenhum, o controlador ve o valor padrao do atributo. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSemMiddlewareOAtributoCaiNoPadrao(): void
    {
        $this->prepararRequisicao();

        ob_start();
        (new Cubo(self::APP))->run();
        $saida = (string) ob_get_clean();

        $this->assertStringContainsString('<i>anonimo</i>', $saida);
    }

    /** Middleware que rejeita nao deixa o controlador renderizar. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMiddlewareQueRejeitaImpedeARenderizacao(): void
    {
        $this->prepararRequisicao();

        $barra = new class implements Middleware {
            public function handle(Request $request, \Closure $next): Response
            {
                return Response::text('barrado', 401);
            }
        };

        ob_start();
        (new Cubo(self::APP))->middleware($barra)->run();
        $saida = (string) ob_get_clean();

        $this->assertSame('barrado', $saida);
        $this->assertStringNotContainsString(EchoController::MARCADOR, $saida);
    }
}
