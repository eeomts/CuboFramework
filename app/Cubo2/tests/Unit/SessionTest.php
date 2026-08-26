<?php

namespace Cubo\Tests\Unit;

use Cubo\Session;
use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Session::class)]
final class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        // Instancia sem o construtor para nao disparar session_start() (efeito
        // colateral global). Aqui testamos so a logica de dados sobre $_SESSION.
        $this->session = (new ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testSetGravaChaveDeTopo(): void
    {
        $this->session->set('login', ['nome' => 'joao']);

        $this->assertSame(['nome' => 'joao'], $_SESSION['login']);
    }

    public function testGetLeValorAninhadoPorCaminho(): void
    {
        $_SESSION = ['login' => ['nome' => 'joao']];

        $this->assertSame('joao', $this->session->get('login.nome'));
    }

    public function testGetRetornaDefaultQuandoCaminhoNaoExiste(): void
    {
        $_SESSION = ['login' => ['nome' => 'joao']];

        $this->assertSame('', $this->session->get('login.idade'));
        $this->assertSame('n/a', $this->session->get('nada', 'n/a'));
    }

    public function testAllRetornaSessaoInteira(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];

        $this->assertSame(['a' => 1, 'b' => 2], $this->session->all());
    }

    public function testRemoveApagaChaveDeTopo(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];

        $this->session->remove('a');

        $this->assertSame(['b' => 2], $_SESSION);
    }

    public function testRemoveApagaValorAninhado(): void
    {
        $_SESSION = ['login' => ['nome' => 'joao', 'idade' => 30]];

        $this->session->remove('login.idade');

        $this->assertSame(['login' => ['nome' => 'joao']], $_SESSION);
    }

    public function testRemoveIgnoraCaminhoInexistente(): void
    {
        $_SESSION = ['login' => ['nome' => 'joao']];

        $this->session->remove('login.telefone.ddd');

        $this->assertSame(['login' => ['nome' => 'joao']], $_SESSION);
    }
}
