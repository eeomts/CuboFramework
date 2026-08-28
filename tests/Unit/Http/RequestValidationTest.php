<?php

namespace Cubo\Tests\Unit\Http;

use Cubo\Http\Request;
use Cubo\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class RequestValidationTest extends TestCase
{
    public function testValidateComSucesso(): void
    {
        $request = new Request(
            post: ['nome' => 'João', 'email' => 'joao@test.com']
        );

        $validated = $request->validate([
            'nome' => 'required|min:3',
            'email' => 'required|email',
        ]);

        $this->assertSame(['nome' => 'João', 'email' => 'joao@test.com'], $validated);
    }

    public function testValidateComFalha(): void
    {
        $request = new Request(
            post: ['nome' => '']
        );

        $this->expectException(ValidationException::class);

        $request->validate(['nome' => 'required']);
    }


    /** So os campos declarados nas regras voltam; o resto e descartado. */
    public function testValidateDevolveApenasOsCamposDeclarados(): void
    {
        $request = new Request(
            post: ['nome' => 'Joao', 'idade' => 25, 'extra' => 'ignorado']
        );

        $validated = $request->validate([
            'nome' => 'required',
            'idade' => 'numeric',
        ]);

        $this->assertArrayHasKey('nome', $validated);
        $this->assertArrayHasKey('idade', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function testValidateGetEPost(): void
    {
        $request = new Request(
            get: ['pagina' => '1'],
            post: ['nome' => 'João']
        );

        $validated = $request->validate([
            'pagina' => 'numeric',
            'nome' => 'required',
        ]);

        $this->assertSame('1', $validated['pagina']);
        $this->assertSame('João', $validated['nome']);
    }

    public function testExceptionComErros(): void
    {
        $request = new Request(
            post: ['nome' => '', 'email' => 'invalido']
        );

        try {
            $request->validate([
                'nome' => 'required',
                'email' => 'email',
            ]);
            $this->fail('ValidationException não foi lançada');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->getErrors());
            $flat = $e->getMessagesFlat();
            $this->assertArrayHasKey('nome', $flat);
            $this->assertArrayHasKey('email', $flat);
        }
    }
}
