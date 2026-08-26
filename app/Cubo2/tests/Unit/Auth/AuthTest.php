<?php

namespace Cubo\Tests\Unit\Auth;

use Cubo\Auth\ApiKey;
use Cubo\Auth\Auth;
use Cubo\Tests\Support\Auth\FakeApiKeyRepository;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private const POST = ['REQUEST_METHOD' => 'POST'];

    public function testSemHeaderAuthorizationNaoAutoriza(): void
    {
        $auth = new Auth(new FakeApiKeyRepository());

        $auth->authenticate([], self::POST);

        $this->assertFalse($auth->isAuthorized());
        $this->assertStringContainsString('API Authorization is necessary', $auth->getMessage());
    }

    public function testHeaderAuthorizationMalformadoNaoAutoriza(): void
    {
        $repo = new FakeApiKeyRepository();
        $auth = new Auth($repo);

        // Sem ":" -- no v1 isto virava "Undefined array key 1" e seguia adiante.
        $auth->authenticate(['Authorization' => 'sem-separador'], self::POST);

        $this->assertFalse($auth->isAuthorized());
        $this->assertSame([], $repo->calls, 'nem deveria consultar o repositorio');
    }

    public function testCredenciaisInvalidasNaoAutorizam(): void
    {
        $auth = new Auth(new FakeApiKeyRepository(null));

        $auth->authenticate(['Authorization' => 'id:secreto'], self::POST);

        $this->assertFalse($auth->isAuthorized());
        $this->assertSame('No connection. Check your credentials.', $auth->getMessage());
    }

    public function testMensagemDeErroNaoEcoaAsCredenciais(): void
    {
        // O v1 fazia: "Check your credentials." . $app_id . " - " . $app_secret
        $auth = new Auth(new FakeApiKeyRepository(null));

        $auth->authenticate(['Authorization' => 'meu-id:meu-segredo'], self::POST);

        $this->assertStringNotContainsString('meu-id', $auth->getMessage());
        $this->assertStringNotContainsString('meu-segredo', $auth->getMessage());
    }

    public function testCredencialChegaCruaAoRepositorioParaSerBindada(): void
    {
        // A defesa contra SQLi e o bind no repositorio, nao uma limpeza no
        // caminho: o valor tem de chegar la exatamente como veio no header.
        $payload = "' OR '1'='1";
        $repo = new FakeApiKeyRepository(null);
        $auth = new Auth($repo);

        $auth->authenticate(['Authorization' => "app{$payload}:sec{$payload}"], self::POST);

        $this->assertFalse($auth->isAuthorized());
        $this->assertSame(
            [['appId' => "app{$payload}", 'appSecret' => "sec{$payload}"]],
            $repo->calls,
        );
    }

    public function testSegredoComDoisPontosNaoEhTruncado(): void
    {
        $repo = new FakeApiKeyRepository(null);
        $auth = new Auth($repo);

        $auth->authenticate(['Authorization' => 'id:a:b:c'], self::POST);

        $this->assertSame('a:b:c', $repo->calls[0]['appSecret']);
    }

    public function testAutorizaQuandoCredencialEHostConferem(): void
    {
        $auth = new Auth(new FakeApiKeyRepository(new ApiKey(344, 'cliente.com.br')));

        $auth->authenticate([
            'Authorization' => 'id:secreto',
            'Referer' => 'https://app.cliente.com.br/painel',
            'Origin' => 'https://app.cliente.com.br',
        ], self::POST);

        $this->assertTrue($auth->isAuthorized());
        $this->assertSame(344, $auth->getConta());
        $this->assertSame('Authorized Connection.', $auth->getMessage());
    }

    public function testNegaQuandoORefererEhDeOutroHost(): void
    {
        $auth = new Auth(new FakeApiKeyRepository(new ApiKey(344, 'cliente.com.br')));

        $auth->authenticate([
            'Authorization' => 'id:secreto',
            'Referer' => 'https://evil.com',
        ], self::POST);

        $this->assertFalse($auth->isAuthorized());
        $this->assertStringContainsString('no authorization for Your url', $auth->getMessage());
    }

    public function testNaoDevolveContaQuandoNegadoPorHost(): void
    {
        $auth = new Auth(new FakeApiKeyRepository(new ApiKey(344, 'cliente.com.br')));

        $auth->authenticate([
            'Authorization' => 'id:secreto',
            'Referer' => 'https://evil.com',
        ], self::POST);

        // O v1 preenchia $this->conta ANTES de checar o host.
        $this->assertSame(0, $auth->getConta());
    }

    public function testAusenciaDeRefererNegaAcesso(): void
    {
        $auth = new Auth(new FakeApiKeyRepository(new ApiKey(344, 'cliente.com.br')));

        $auth->authenticate(['Authorization' => 'id:secreto'], self::POST);

        $this->assertFalse($auth->isAuthorized());
    }

    public function testAusenciaDeRefererNegaAcessoMesmoComUrlAccessCoringa(): void
    {
        // Detalhe sutil do v1 mantido: o else de checkHost() negava sem Referer
        // INCLUSIVE quando url_access era '%'.
        $auth = new Auth(new FakeApiKeyRepository(new ApiKey(344, '%')));

        $auth->authenticate(['Authorization' => 'id:secreto'], self::POST);

        $this->assertFalse($auth->isAuthorized());
    }

    public function testUrlAccessCoringaAutorizaQualquerRefererPresente(): void
    {
        $auth = new Auth(new FakeApiKeyRepository(new ApiKey(344, '%')));

        $auth->authenticate([
            'Authorization' => 'id:secreto',
            'Referer' => 'https://qualquer-um.com',
        ], self::POST);

        $this->assertTrue($auth->isAuthorized());
    }

    public function testHeaderEhLidoIgnorandoACaixaDoNome(): void
    {
        $auth = new Auth(new FakeApiKeyRepository(new ApiKey(344, 'cliente.com.br')));

        $auth->authenticate([
            'authorization' => 'id:secreto',
            'REFERER' => 'https://cliente.com.br',
        ], self::POST);

        $this->assertTrue($auth->isAuthorized());
    }

    public function testPreflightNaoAutenticaNemConsultaORepositorio(): void
    {
        $repo = new FakeApiKeyRepository(new ApiKey(344, 'cliente.com.br'));
        $auth = new Auth($repo);

        $auth->authenticate(
            ['Origin' => 'https://app.cliente.com.br'],
            ['REQUEST_METHOD' => 'OPTIONS'],
        );

        $this->assertTrue($auth->isPreflight());
        $this->assertFalse($auth->isAuthorized());
        $this->assertSame([], $repo->calls);
    }

    public function testRequisicaoNormalNaoEhMarcadaComoPreflight(): void
    {
        $auth = new Auth(new FakeApiKeyRepository(null));

        $auth->authenticate(['Authorization' => 'id:secreto'], self::POST);

        $this->assertFalse($auth->isPreflight());
    }
}
