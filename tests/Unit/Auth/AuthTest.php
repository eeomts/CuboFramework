<?php

namespace Cubo\Tests\Unit\Auth;

use Cubo\Auth\ApiKey;
use Cubo\Auth\Auth;
use Cubo\Tests\Support\Auth\FakeApiKeyRepository;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private const POST = ['REQUEST_METHOD' => 'POST'];

    /** O segredo que os testes enviam no header. */
    private const SEGREDO = 'secreto';

    /** Chave ativa cujo hash corresponde a self::SEGREDO. */
    private function chave(string $urlAccess, string $segredo = self::SEGREDO): ApiKey
    {
        return new ApiKey(344, ApiKey::hashSecret($segredo), $urlAccess);
    }

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

    public function testAppIdInexistenteNaoAutoriza(): void
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

    public function testAppIdChegaCruoAoRepositorioParaSerBindado(): void
    {
        // A defesa contra SQLi e o bind no repositorio, nao uma limpeza no
        // caminho: o valor tem de chegar la exatamente como veio no header.
        $payload = "' OR '1'='1";
        $repo = new FakeApiKeyRepository(null);
        $auth = new Auth($repo);

        $auth->authenticate(['Authorization' => "app{$payload}:qualquer"], self::POST);

        $this->assertFalse($auth->isAuthorized());
        $this->assertSame(["app{$payload}"], $repo->calls);
    }

    // --- o segredo, que agora e comparado por hash dentro do Auth ---

    /** O segredo NAO entra na consulta: o repositorio so recebe o app_id. */
    public function testOSegredoNaoChegaAoRepositorio(): void
    {
        $repo = new FakeApiKeyRepository($this->chave('%'));
        $auth = new Auth($repo);

        $auth->authenticate([
            'Authorization' => 'id:' . self::SEGREDO,
            'Referer' => 'https://qualquer-um.com',
        ], self::POST);

        $this->assertSame(['id'], $repo->calls);
    }

    public function testSegredoErradoNaoAutorizaMesmoComAppIdValido(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('%')));

        $auth->authenticate([
            'Authorization' => 'id:segredo-errado',
            'Referer' => 'https://qualquer-um.com',
        ], self::POST);

        $this->assertFalse($auth->isAuthorized());
        $this->assertSame('No connection. Check your credentials.', $auth->getMessage());
    }

    /**
     * A collation padrao do Cubo (utf8mb4_unicode_ci) ignora caixa, entao
     * comparar o segredo em SQL deixaria 'SECRETO' passar por 'secreto'.
     */
    public function testAComparacaoRespeitaACaixaDoSegredo(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('%')));

        $auth->authenticate([
            'Authorization' => 'id:' . strtoupper(self::SEGREDO),
            'Referer' => 'https://qualquer-um.com',
        ], self::POST);

        $this->assertFalse($auth->isAuthorized());
    }

    /** A comparacao em si e byte a byte: espaco conta. */
    public function testSecretMatchesNaoIgnoraEspacoNasPontas(): void
    {
        $chave = new ApiKey(1, ApiKey::hashSecret(self::SEGREDO));

        $this->assertFalse($chave->secretMatches(self::SEGREDO . ' '));
        $this->assertFalse($chave->secretMatches(' ' . self::SEGREDO));
    }

    /**
     * MAS o Credentials apara as pontas antes de comparar, entao um segredo nao
     * pode comecar nem terminar com espaco -- ele seria inutilizavel.
     * Comportamento anterior a esta mudanca, fixado aqui para ficar visivel.
     */
    public function testCredentialsAparaEspacoDasPontasDoSegredo(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('%')));

        $auth->authenticate([
            'Authorization' => 'id:  ' . self::SEGREDO . '   ',
            'Referer' => 'https://qualquer-um.com',
        ], self::POST);

        $this->assertTrue($auth->isAuthorized());
    }

    public function testSegredoComDoisPontosFunciona(): void
    {
        $segredo = 'a:b:c';
        $auth = new Auth(new FakeApiKeyRepository($this->chave('%', $segredo)));

        $auth->authenticate([
            'Authorization' => 'id:' . $segredo,
            'Referer' => 'https://qualquer-um.com',
        ], self::POST);

        $this->assertTrue($auth->isAuthorized(), 'so o primeiro ":" separa');
    }

    public function testHashSecretGeraHashVerificavelENaoOTextoPuro(): void
    {
        $hash = ApiKey::hashSecret('minha-senha');

        $this->assertNotSame('minha-senha', $hash);
        $this->assertTrue((new ApiKey(1, $hash))->secretMatches('minha-senha'));
        $this->assertFalse((new ApiKey(1, $hash))->secretMatches('outra'));
    }

    /** Hash vazio ou corrompido na coluna nao pode virar "qualquer segredo serve". */
    public function testHashInvalidoNuncaCasa(): void
    {
        foreach (['', 'nao-e-hash', '$2y$10$curto'] as $hashRuim) {
            $chave = new ApiKey(1, $hashRuim);

            $this->assertFalse($chave->secretMatches(''), "hash '{$hashRuim}' com segredo vazio");
            $this->assertFalse($chave->secretMatches('qualquer'), "hash '{$hashRuim}'");
        }
    }

    // --- allowlist de host, inalterada ---

    public function testAutorizaQuandoCredencialEHostConferem(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('cliente.com.br')));

        $auth->authenticate([
            'Authorization' => 'id:' . self::SEGREDO,
            'Referer' => 'https://app.cliente.com.br/painel',
            'Origin' => 'https://app.cliente.com.br',
        ], self::POST);

        $this->assertTrue($auth->isAuthorized());
        $this->assertSame(344, $auth->getConta());
        $this->assertSame('Authorized Connection.', $auth->getMessage());
    }

    public function testNegaQuandoORefererEhDeOutroHost(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('cliente.com.br')));

        $auth->authenticate([
            'Authorization' => 'id:' . self::SEGREDO,
            'Referer' => 'https://evil.com',
        ], self::POST);

        $this->assertFalse($auth->isAuthorized());
        $this->assertStringContainsString('no authorization for Your url', $auth->getMessage());
    }

    public function testNaoDevolveContaQuandoNegadoPorHost(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('cliente.com.br')));

        $auth->authenticate([
            'Authorization' => 'id:' . self::SEGREDO,
            'Referer' => 'https://evil.com',
        ], self::POST);

        // O v1 preenchia $this->conta ANTES de checar o host.
        $this->assertSame(0, $auth->getConta());
    }

    public function testAusenciaDeRefererNegaAcesso(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('cliente.com.br')));

        $auth->authenticate(['Authorization' => 'id:' . self::SEGREDO], self::POST);

        $this->assertFalse($auth->isAuthorized());
    }

    public function testAusenciaDeRefererNegaAcessoMesmoComUrlAccessCoringa(): void
    {
        // Detalhe sutil do v1 mantido: o else de checkHost() negava sem Referer
        // INCLUSIVE quando url_access era '%'.
        $auth = new Auth(new FakeApiKeyRepository($this->chave('%')));

        $auth->authenticate(['Authorization' => 'id:' . self::SEGREDO], self::POST);

        $this->assertFalse($auth->isAuthorized());
    }

    public function testUrlAccessCoringaAutorizaQualquerRefererPresente(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('%')));

        $auth->authenticate([
            'Authorization' => 'id:' . self::SEGREDO,
            'Referer' => 'https://qualquer-um.com',
        ], self::POST);

        $this->assertTrue($auth->isAuthorized());
    }

    public function testHeaderEhLidoIgnorandoACaixaDoNome(): void
    {
        $auth = new Auth(new FakeApiKeyRepository($this->chave('cliente.com.br')));

        $auth->authenticate([
            'authorization' => 'id:' . self::SEGREDO,
            'REFERER' => 'https://cliente.com.br',
        ], self::POST);

        $this->assertTrue($auth->isAuthorized());
    }

    public function testPreflightNaoAutenticaNemConsultaORepositorio(): void
    {
        $repo = new FakeApiKeyRepository($this->chave('cliente.com.br'));
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
