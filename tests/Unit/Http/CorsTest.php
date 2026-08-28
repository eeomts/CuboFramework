<?php

namespace Cubo\Tests\Unit\Http;

use Cubo\Http\Cors;
use PHPUnit\Framework\TestCase;

final class CorsTest extends TestCase
{
    public function testWildcardLiberaQualquerOrigem(): void
    {
        $cors = new Cors('%');

        $this->assertTrue($cors->allowsAnyOrigin());
        $this->assertTrue($cors->allows('https://qualquer-um.com'));
    }

    public function testSemHostConfiguradoLiberaQualquerOrigem(): void
    {
        // Fiel ao v1: !empty($restrict_host) && $restrict_host <> "%"
        $this->assertTrue((new Cors(null))->allows('https://qualquer-um.com'));
        $this->assertTrue((new Cors(''))->allows('https://qualquer-um.com'));
        $this->assertTrue((new Cors('   '))->allows('https://qualquer-um.com'));
    }

    public function testAutorizaOrigemQueBateComOUrlAccess(): void
    {
        $cors = new Cors('app.cliente.com.br');

        $this->assertFalse($cors->allowsAnyOrigin());
        $this->assertTrue($cors->allows('https://app.cliente.com.br'));
    }

    public function testNegaOrigemDeOutroHost(): void
    {
        $cors = new Cors('app.cliente.com.br');

        $this->assertFalse($cors->allows('https://evil.com'));
        $this->assertSame([], $cors->headersFor('https://evil.com'));
    }

    public function testNaoCaiNoMatchParcialQueOV1Permitia(): void
    {
        // O v1 fazia preg_match("/{$restrict_host}/i", $referer), match PARCIAL:
        // qualquer string CONTENDO "cliente.com" passava. Aqui nao passa mais.
        $cors = new Cors('cliente.com');

        $this->assertFalse($cors->allows('https://evil.com/?x=cliente.com'));
        $this->assertFalse($cors->allows('https://cliente.com.evil.net'));
        $this->assertFalse($cors->allows('https://evil-cliente.com'));
    }

    public function testAceitaSubdominioDoHostPermitido(): void
    {
        // Quem cadastra "cliente.com.br" quer que "app.cliente.com.br" valha.
        $cors = new Cors('cliente.com.br');

        $this->assertTrue($cors->allows('https://cliente.com.br'));
        $this->assertTrue($cors->allows('https://app.cliente.com.br'));
        $this->assertTrue($cors->allows('https://painel.interno.cliente.com.br'));
    }

    public function testSubdominioNaoAfrouxaOsBypasses(): void
    {
        // O ponto separador e exigido, entao os casos que o regex do v1 deixava
        // passar continuam bloqueados mesmo com subdominio permitido.
        $cors = new Cors('cliente.com.br');

        $this->assertFalse($cors->allows('https://cliente.com.br.evil.net'));
        $this->assertFalse($cors->allows('https://evil-cliente.com.br'));
    }

    public function testPontoDoDominioNaoFuncionaComoCoringa(): void
    {
        // No regex do v1 o "." casava qualquer caractere, entao "cliente.com"
        // tambem casava "clienteXcom".
        $cors = new Cors('cliente.com');

        $this->assertFalse($cors->allows('https://clienteXcom'));
    }

    public function testAceitaUrlAccessComOuSemEsquema(): void
    {
        $origem = 'https://app.cliente.com.br';

        $this->assertTrue((new Cors('app.cliente.com.br'))->allows($origem));
        $this->assertTrue((new Cors('https://app.cliente.com.br'))->allows($origem));
        $this->assertTrue((new Cors('https://app.cliente.com.br/painel'))->allows($origem));
        $this->assertTrue((new Cors('//app.cliente.com.br'))->allows($origem));
    }

    public function testComparacaoDeHostIgnoraCaixa(): void
    {
        $cors = new Cors('App.Cliente.COM.br');

        $this->assertTrue($cors->allows('https://APP.cliente.com.BR'));
    }

    public function testOrigemAusenteNaoGeraCabecalhoNenhum(): void
    {
        $cors = new Cors('%');

        $this->assertSame([], $cors->headersFor(null));
        $this->assertSame([], $cors->headersFor(''));
    }

    public function testCabecalhosDaRequisicaoNormal(): void
    {
        $cors = new Cors('app.cliente.com.br');

        $headers = $cors->headersFor('https://app.cliente.com.br');

        $this->assertSame('https://app.cliente.com.br', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('86400', $headers['Access-Control-Max-Age']);
        // Correcao do v1: sem Vary um cache compartilhado serviria a resposta
        // de uma origem para outra.
        $this->assertSame('Origin', $headers['Vary']);
    }

    public function testAllowCredentialsDesligadoPorPadrao(): void
    {
        $cors = new Cors('app.cliente.com.br');

        $headers = $cors->headersFor('https://app.cliente.com.br');

        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $headers);
    }

    public function testAllowCredentialsQuandoLigadoExplicitamente(): void
    {
        $cors = new Cors('app.cliente.com.br', true);

        $headers = $cors->headersFor('https://app.cliente.com.br');

        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
    }

    public function testPreflightAcrescentaMetodosEHeadersPedidos(): void
    {
        $cors = new Cors('app.cliente.com.br');

        $headers = $cors->preflightHeadersFor('https://app.cliente.com.br', 'Authorization, Content-Type');

        $this->assertSame('GET, POST, OPTIONS', $headers['Access-Control-Allow-Methods']);
        $this->assertSame('Authorization, Content-Type', $headers['Access-Control-Allow-Headers']);
    }

    public function testPreflightSemHeadersPedidosNaoEmiteAllowHeaders(): void
    {
        $cors = new Cors('app.cliente.com.br');

        $headers = $cors->preflightHeadersFor('https://app.cliente.com.br');

        $this->assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Headers', $headers);
    }

    public function testPreflightDeOrigemNegadaNaoEmiteNada(): void
    {
        $cors = new Cors('app.cliente.com.br');

        $this->assertSame([], $cors->preflightHeadersFor('https://evil.com', 'Authorization'));
    }

    public function testIsPreflight(): void
    {
        $this->assertTrue(Cors::isPreflight(['REQUEST_METHOD' => 'OPTIONS']));
        $this->assertFalse(Cors::isPreflight(['REQUEST_METHOD' => 'POST']));
        $this->assertFalse(Cors::isPreflight([]));
    }

    // --- guarda de credenciais e verbos anunciados ---

    /**
     * Refletir qualquer origem E liberar credenciais entregaria resposta
     * autenticada a qualquer site: a combinacao e recusada na construcao.
     */
    public function testCredenciaisComOrigemAbertaEhRecusado(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/host declarado/');

        new Cors(allowedHost: null, allowCredentials: true);
    }

    public function testCredenciaisComWildcardTambemEhRecusado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Cors(allowedHost: '%', allowCredentials: true);
    }

    public function testCredenciaisComHostDeclaradoEhAceito(): void
    {
        $cors = new Cors(allowedHost: 'cliente.com', allowCredentials: true);

        $headers = $cors->headersFor('https://cliente.com');

        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
    }

    public function testPreflightAnunciaOsVerbosPadrao(): void
    {
        $cors = new Cors('cliente.com');

        $headers = $cors->preflightHeadersFor('https://cliente.com');

        $this->assertSame('GET, POST, OPTIONS', $headers['Access-Control-Allow-Methods']);
    }

    /** Sem isso, PUT e DELETE seriam bloqueados pelo navegador quando o
     *  roteamento por verbo entrar. */
    public function testPreflightAnunciaOsVerbosDeclarados(): void
    {
        $cors = new Cors('cliente.com', allowedMethods: ['GET', 'PUT', 'DELETE', 'OPTIONS']);

        $headers = $cors->preflightHeadersFor('https://cliente.com');

        $this->assertSame('GET, PUT, DELETE, OPTIONS', $headers['Access-Control-Allow-Methods']);
    }
}
