<?php

namespace Cubo\Tests\Unit\Validation;

use Cubo\Validation\Validator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
    public function testRequiredComValor(): void
    {
        $validator = new Validator(['nome' => 'João'], ['nome' => 'required']);
        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->getErrors());
    }

    public function testRequiredSemValor(): void
    {
        $validator = new Validator(['nome' => ''], ['nome' => 'required']);
        $this->assertFalse($validator->validate());
        $this->assertNotEmpty($validator->getErrors());
    }

    public function testEmailValido(): void
    {
        $validator = new Validator(['email' => 'joao@test.com'], ['email' => 'email']);
        $this->assertTrue($validator->validate());
    }

    public function testEmailInvalido(): void
    {
        $validator = new Validator(['email' => 'nao-e-email'], ['email' => 'email']);
        $this->assertFalse($validator->validate());
    }

    public function testMinimo(): void
    {
        $validator = new Validator(['nome' => 'ab'], ['nome' => 'min:3']);
        $this->assertFalse($validator->validate());

        $validator = new Validator(['nome' => 'abc'], ['nome' => 'min:3']);
        $this->assertTrue($validator->validate());
    }

    public function testMaximo(): void
    {
        $validator = new Validator(['nome' => 'abcdef'], ['nome' => 'max:5']);
        $this->assertFalse($validator->validate());

        $validator = new Validator(['nome' => 'abcde'], ['nome' => 'max:5']);
        $this->assertTrue($validator->validate());
    }

    public function testNumeric(): void
    {
        $validator = new Validator(['idade' => '25'], ['idade' => 'numeric']);
        $this->assertTrue($validator->validate());

        $validator = new Validator(['idade' => 'abc'], ['idade' => 'numeric']);
        $this->assertFalse($validator->validate());
    }

    public function testCpf(): void
    {
        // CPF válido (11111111191 é válido)
        $validator = new Validator(['cpf' => '11144477735'], ['cpf' => 'cpf']);
        $this->assertTrue($validator->validate());

        $validator = new Validator(['cpf' => '123'], ['cpf' => 'cpf']);
        $this->assertFalse($validator->validate());
    }

    public function testCnpj(): void
    {
        // CNPJ válido
        $validator = new Validator(['cnpj' => '11222333000181'], ['cnpj' => 'cnpj']);
        $this->assertTrue($validator->validate());

        $validator = new Validator(['cnpj' => '123'], ['cnpj' => 'cnpj']);
        $this->assertFalse($validator->validate());
    }

    public function testUrl(): void
    {
        $validator = new Validator(['site' => 'https://example.com'], ['site' => 'url']);
        $this->assertTrue($validator->validate());

        $validator = new Validator(['site' => 'nao-e-url'], ['site' => 'url']);
        $this->assertFalse($validator->validate());
    }

    public function testConfirmed(): void
    {
        $validator = new Validator(
            ['password' => 'abc123', 'password_confirmation' => 'abc123'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($validator->validate());

        $validator = new Validator(
            ['password' => 'abc123', 'password_confirmation' => 'diferente'],
            ['password' => 'confirmed']
        );
        $this->assertFalse($validator->validate());
    }

    public function testMultiplosRegras(): void
    {
        $validator = new Validator(
            ['email' => 'joao@test.com'],
            ['email' => 'required|email|max:100']
        );
        $this->assertTrue($validator->validate());
    }

    public function testOptionalNaoRequerido(): void
    {
        $validator = new Validator(['nome' => ''], ['nome' => 'email']);
        $this->assertTrue($validator->validate());
    }

    public function testErrosPorCampo(): void
    {
        $validator = new Validator(
            ['nome' => '', 'email' => 'invalido'],
            ['nome' => 'required', 'email' => 'email']
        );
        $validator->validate();

        $errors = $validator->getErrors();
        $this->assertArrayHasKey('nome', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    /** 'Joao' com acento tem 4 caracteres e 5 bytes; a regra conta caracteres. */
    public function testMaxContaCaracteresENaoBytes(): void
    {
        $validator = new Validator(['nome' => 'João'], ['nome' => 'max:4']);

        $this->assertTrue($validator->validate(), "'João' tem 4 caracteres, deve passar em max:4");
    }

    /** 'cao' com cedilha e til tem 3 caracteres e 5 bytes; por bytes passaria errado. */
    public function testMinContaCaracteresENaoBytes(): void
    {
        $validator = new Validator(['nome' => 'ção'], ['nome' => 'min:4']);

        $this->assertFalse($validator->validate(), "'ção' tem 3 caracteres, deve reprovar em min:4");
    }

    /** Regra escrita errada nao pode desligar a validacao em silencio. */
    public function testRegraDesconhecidaEstoura(): void
    {
        $validator = new Validator(['email' => 'nao-e-email'], ['email' => 'emial']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/emial/');

        $validator->validate();
    }

    public function testRegraVaziaNaListaEhIgnorada(): void
    {
        $validator = new Validator(['nome' => 'Ana'], ['nome' => 'required|']);

        $this->assertTrue($validator->validate());
    }

    /**
     * Em array, min/max contam elementos. Os numeros aqui sao escolhidos para
     * separar do bug antigo: (string) de um array vira "Array", 5 caracteres.
     */
    public function testMaxContaElementosQuandoOValorEhArray(): void
    {
        // 2 elementos cabem em max:3; medindo "Array" (5) reprovaria
        $validator = new Validator(['tags' => ['a', 'b']], ['tags' => 'max:3']);

        $this->assertTrue($validator->validate());
    }

    public function testMinContaElementosQuandoOValorEhArray(): void
    {
        // 3 elementos nao alcancam min:6; medindo "Array" (5)... tambem nao,
        // entao invertemos: 6 elementos passam em min:6, "Array" reprovaria
        $validator = new Validator(['tags' => ['a', 'b', 'c', 'd', 'e', 'f']], ['tags' => 'min:6']);

        $this->assertTrue($validator->validate());
    }

    /** Como toda outra regra, 'confirmed' ignora campo vazio sem 'required'. */
    public function testConfirmedIgnoraCampoOpcionalVazio(): void
    {
        $validator = new Validator(['senha' => ''], ['senha' => 'confirmed']);

        $this->assertTrue($validator->validate());
    }

    public function testColetaTodosOsErrosDoMesmoCampo(): void
    {
        $validator = new Validator(['email' => 'x'], ['email' => 'email|min:10']);

        $validator->validate();

        $this->assertCount(2, $validator->getErrors()['email']);
    }

    public function testErrosFlat(): void
    {
        $validator = new Validator(
            ['nome' => '', 'email' => 'invalido'],
            ['nome' => 'required', 'email' => 'email']
        );
        $validator->validate();

        $flat = $validator->getErrorsFlat();
        $this->assertIsString($flat['nome']);
        $this->assertIsString($flat['email']);
    }
}
