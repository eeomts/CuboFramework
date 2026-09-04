<?php

namespace Cubo\Tests\Unit\Http;

use Cubo\Http\Request;
use Cubo\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
final class RequestSanitizeTest extends TestCase
{
    public function testSanitizeNormalizaOsCamposDeclarados(): void
    {
        $req = new Request(post: ['mon_valor' => 'R$ 1.234,56', 'data_venc' => '05/01/2026']);

        $dados = $req->sanitize(['mon_valor' => 'money', 'data_venc' => 'date']);

        $this->assertSame('1234.56', $dados['mon_valor']);
        $this->assertSame('2026-01-05', $dados['data_venc']);
    }

    /**
     * Diferenca proposital para o validate(), que devolve so os campos das regras:
     * sanitizar dois campos de dez nao pode sumir com os outros oito.
     */
    public function testSanitizeDevolveTambemOsCamposNaoDeclarados(): void
    {
        $req = new Request(post: ['mon_valor' => '10,50', 'nome' => 'ana']);

        $dados = $req->sanitize(['mon_valor' => 'money']);

        $this->assertSame('ana', $dados['nome']);
    }

    public function testSanitizeJuntaGetEPost(): void
    {
        $req = new Request(get: ['num_pagina' => '2'], post: ['mon_valor' => '10,50']);

        $dados = $req->sanitize(['num_pagina' => 'int', 'mon_valor' => 'money']);

        $this->assertSame(2, $dados['num_pagina']);
        $this->assertSame('10.50', $dados['mon_valor']);
    }

    public function testSanitizeEstouraComOsMesmosErrosDoValidate(): void
    {
        $req = new Request(post: ['data_venc' => '31/02/2026']);

        try {
            $req->sanitize(['data_venc' => 'date']);
            $this->fail('esperava ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['data_venc' => 'data_venc nao e uma data valida'],
                $e->getMessagesFlat()
            );
        }
    }

    /** Campo vazio nao e erro: vira null e quem cobra a falta e o `required` do validate. */
    public function testCampoVazioAtravessaComoNull(): void
    {
        $req = new Request(post: ['data_venc' => '', 'mon_valor' => '']);

        $dados = $req->sanitize(['data_venc' => 'date', 'mon_valor' => 'money']);

        $this->assertNull($dados['data_venc']);
        $this->assertNull($dados['mon_valor']);
    }

    /** O par sanitize -> validate e o fluxo que substitui os traits do app. */
    public function testSanitizeEValidateEncadeiam(): void
    {
        $req = new Request(post: [
            'mon_valor' => 'R$ 1.234,56',
            'data_venc' => '05/01/2026',
            'email' => 'ana@test.com',
        ]);

        $dados = $req->sanitize(['mon_valor' => 'money', 'data_venc' => 'date']);
        $validados = $req->validate(['email' => 'required|email']);

        $this->assertSame('1234.56', $dados['mon_valor']);
        $this->assertSame(['email' => 'ana@test.com'], $validados);
    }
}
