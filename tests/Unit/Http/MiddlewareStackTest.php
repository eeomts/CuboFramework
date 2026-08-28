<?php

namespace Cubo\Tests\Unit\Http;

use Cubo\Http\Middleware;
use Cubo\Http\MiddlewareStack;
use Cubo\Http\Request;
use Cubo\Http\Response;
use PHPUnit\Framework\TestCase;

final class MiddlewareStackTest extends TestCase
{
    public function testSemMiddleware(): void
    {
        $stack = new MiddlewareStack();
        $called = false;

        $response = $stack->execute(new Request(), function() use (&$called) {
            $called = true;
            return Response::text('OK');
        });

        $this->assertTrue($called);
        $this->assertSame('OK', $response->getBody());
    }

    public function testUmMiddleware(): void
    {
        $order = [];
        $mw = new class($order) implements Middleware {
            public function __construct(private array &$order) {}
            public function handle(Request $request, \Closure $next): Response {
                $this->order[] = 'mw:before';
                $response = $next($request);
                $this->order[] = 'mw:after';
                return $response;
            }
        };

        $stack = new MiddlewareStack();
        $stack->add($mw);

        $response = $stack->execute(new Request(), function() use (&$order) {
            $order[] = 'final';
            return Response::text('OK');
        });

        $this->assertSame(['mw:before', 'final', 'mw:after'], $order);
    }

    public function testDoisMiddlewares(): void
    {
        $order = [];

        $mw1 = new class($order) implements Middleware {
            public function __construct(private array &$order) {}
            public function handle(Request $request, \Closure $next): Response {
                $this->order[] = 'mw1:before';
                $response = $next($request);
                $this->order[] = 'mw1:after';
                return $response;
            }
        };

        $mw2 = new class($order) implements Middleware {
            public function __construct(private array &$order) {}
            public function handle(Request $request, \Closure $next): Response {
                $this->order[] = 'mw2:before';
                $response = $next($request);
                $this->order[] = 'mw2:after';
                return $response;
            }
        };

        $stack = new MiddlewareStack();
        $stack->add($mw1)->add($mw2);

        $response = $stack->execute(new Request(), function() use (&$order) {
            $order[] = 'final';
            return Response::text('OK');
        });

        $this->assertSame(['mw1:before', 'mw2:before', 'final', 'mw2:after', 'mw1:after'], $order);
    }

    public function testMiddlewareRejeita(): void
    {
        $mw = new class implements Middleware {
            public function handle(Request $request, \Closure $next): Response {
                return Response::json(['error' => 'Rejected'], 403);
            }
        };

        $stack = new MiddlewareStack();
        $stack->add($mw);

        $called = false;
        $response = $stack->execute(new Request(), function() use (&$called) {
            $called = true;
            return Response::text('OK');
        });

        $this->assertFalse($called);
        $this->assertSame(403, $response->getStatus());
    }

    public function testMiddlewareModificaRequest(): void
    {
        $mw = new class implements Middleware {
            public function handle(Request $request, \Closure $next): Response {
                return $next($request);
            }
        };

        $stack = new MiddlewareStack();
        $stack->add($mw);

        $response = $stack->execute(new Request(post: ['nome' => 'João']), function($req) {
            return Response::json(['nome' => $req->post('nome')]);
        });

        $this->assertStringContainsString('João', $response->getBody());
    }

    /**
     * Com o indice guardado no objeto, a segunda chamada a next() continuava de
     * onde a primeira parou e pulava os middlewares seguintes.
     */
    public function testNextChamadoDuasVezesNaoPulaOsMiddlewaresSeguintes(): void
    {
        $trilha = [];

        $duplo = new class($trilha) implements Middleware {
            public function __construct(private array &$trilha) {}
            public function handle(Request $request, \Closure $next): Response {
                $next($request);
                return $next($request);
            }
        };

        $segundo = new class($trilha) implements Middleware {
            public function __construct(private array &$trilha) {}
            public function handle(Request $request, \Closure $next): Response {
                $this->trilha[] = 'segundo';
                return $next($request);
            }
        };

        $stack = new MiddlewareStack();
        $stack->add($duplo)->add($segundo);

        $stack->execute(new Request(server: []), fn() => Response::text('ok'));

        $this->assertSame(['segundo', 'segundo'], $trilha);
    }

    /** Cadeia reentrante: rodar o mesmo stack de novo comeca do zero. */
    public function testStackPodeSerExecutadoMaisDeUmaVez(): void
    {
        $trilha = [];

        $mw = new class($trilha) implements Middleware {
            public function __construct(private array &$trilha) {}
            public function handle(Request $request, \Closure $next): Response {
                $this->trilha[] = 'passou';
                return $next($request);
            }
        };

        $stack = new MiddlewareStack();
        $stack->add($mw);

        $stack->execute(new Request(server: []), fn() => Response::text('ok'));
        $stack->execute(new Request(server: []), fn() => Response::text('ok'));

        $this->assertSame(['passou', 'passou'], $trilha);
    }

    /** Falha na hora de registrar, nao no meio do atendimento. */
    public function testClasseQueNaoImplementaMiddlewareEhRecusadaAoAdicionar(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nao existe ou nao implementa/');

        (new MiddlewareStack())->add(\stdClass::class);
    }
}
