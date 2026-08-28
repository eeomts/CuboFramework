<?php

namespace Cubo\Tests\Unit\Http;

use Cubo\Http\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testJsonFactory(): void
    {
        $data = ['id' => 1, 'nome' => 'João'];
        $res = Response::json($data, 201);

        $this->assertSame(201, $res->getStatus());
        $this->assertSame('application/json', $res->getHeaders()['Content-Type']);
        $this->assertSame('{"id":1,"nome":"João"}', $res->getBody());
    }

    public function testRedirectFactory(): void
    {
        $res = Response::redirect('/clientes', 301);

        $this->assertSame(301, $res->getStatus());
        $this->assertSame('/clientes', $res->getHeaders()['Location']);
    }

    public function testTextFactory(): void
    {
        $res = Response::text('Sucesso', 200);

        $this->assertSame(200, $res->getStatus());
        $this->assertSame('Sucesso', $res->getBody());
    }

    public function testHtmlFactory(): void
    {
        $html = '<h1>Olá</h1>';
        $res = Response::html($html, 200);

        $this->assertSame(200, $res->getStatus());
        $this->assertSame('text/html; charset=utf-8', $res->getHeaders()['Content-Type']);
        $this->assertSame($html, $res->getBody());
    }

    public function testStatusChaining(): void
    {
        $res = Response::text('Erro')->status(404);
        $this->assertSame(404, $res->getStatus());
    }

    public function testHeaderChaining(): void
    {
        $res = Response::text('OK')->header('X-Custom', 'valor');
        $this->assertSame('valor', $res->getHeaders()['X-Custom']);
    }

    public function testBodyChaining(): void
    {
        $res = Response::text('Antes')->body('Depois');
        $this->assertSame('Depois', $res->getBody());
    }

    public function testMultipleHeadersChaining(): void
    {
        $res = Response::text('OK')
            ->header('X-A', '1')
            ->header('X-B', '2')
            ->header('X-C', '3');

        $headers = $res->getHeaders();
        $this->assertSame('1', $headers['X-A']);
        $this->assertSame('2', $headers['X-B']);
        $this->assertSame('3', $headers['X-C']);
    }

    /** JSON com escape de unicode fica ilegivel e maior a toa. */
    public function testJsonNaoEscapaAcento(): void
    {
        $res = Response::json(['nome' => 'João', 'cidade' => 'São Paulo']);

        $this->assertSame('{"nome":"João","cidade":"São Paulo"}', $res->getBody());
    }

    /** Falha de serializacao nao pode virar corpo vazio com status 200. */
    public function testJsonEstouraComDadoNaoSerializavel(): void
    {
        $this->expectException(\JsonException::class);

        Response::json(['recurso' => fopen('php://memory', 'r')]);
    }

    public function testGetters(): void
    {
        $res = Response::json(['test' => true], 201)
            ->header('X-Test', 'valor');

        $this->assertSame(201, $res->getStatus());
        $this->assertNotEmpty($res->getHeaders());
        $this->assertStringContainsString('test', $res->getBody());
    }
}
