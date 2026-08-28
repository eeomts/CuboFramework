<?php

namespace Cubo\Tests\Unit\Http;

use Cubo\Exceptions\StorageException;
use Cubo\Http\Request;
use Cubo\Storage\UploadedFile;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    public function testMethodRetornaVerbaHttpMaiuscula(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'post']);
        $this->assertSame('POST', $req->method());
    }

    public function testMethodPadraoEGet(): void
    {
        $req = new Request();
        $this->assertSame('GET', $req->method());
    }

    public function testIsPost(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'POST']);
        $this->assertTrue($req->isPost());
        $this->assertFalse($req->isGet());
    }

    public function testPostRetornaValorOuTodo(): void
    {
        $req = new Request(post: ['nome' => 'João', 'email' => 'joao@test.com']);
        $this->assertSame('João', $req->post('nome'));
        $this->assertSame(['nome' => 'João', 'email' => 'joao@test.com'], $req->post());
        $this->assertNull($req->post('inexistente'));
    }

    public function testGetRetornaValorOuTodo(): void
    {
        $req = new Request(get: ['pagina' => '2', 'busca' => 'cubo']);
        $this->assertSame('2', $req->get('pagina'));
        $this->assertSame(['pagina' => '2', 'busca' => 'cubo'], $req->get());
    }

    public function testInputBuscaEmPostOuGet(): void
    {
        $req = new Request(
            post: ['nome' => 'João'],
            get: ['email' => 'joao@test.com']
        );
        $this->assertSame('João', $req->input('nome'));
        $this->assertSame('joao@test.com', $req->input('email'));
        $this->assertNull($req->input('inexistente'));
    }

    public function testInputPriorizaPost(): void
    {
        $req = new Request(
            post: ['chave' => 'valor_post'],
            get: ['chave' => 'valor_get']
        );
        $this->assertSame('valor_post', $req->input('chave'));
    }

    public function testAllRetornaMergeDeGetEPost(): void
    {
        $req = new Request(
            get: ['pagina' => '2'],
            post: ['nome' => 'João']
        );
        $this->assertSame(['pagina' => '2', 'nome' => 'João'], $req->all());
    }

    public function testHasVerificaExistenciaEmPostOuGet(): void
    {
        $req = new Request(
            post: ['nome' => 'João'],
            get: ['pagina' => '1']
        );
        $this->assertTrue($req->has('nome'));
        $this->assertTrue($req->has('pagina'));
        $this->assertFalse($req->has('inexistente'));
    }

    public function testFileDevolveUploadedFileValidado(): void
    {
        $req = new Request(files: ['avatar' => [
            'name' => 'avatar.jpg',
            'tmp_name' => '/tmp/php123',
            'size' => 1024,
            'error' => UPLOAD_ERR_OK,
        ]]);

        $arquivo = $req->file('avatar');

        $this->assertInstanceOf(UploadedFile::class, $arquivo);
        $this->assertSame('avatar.jpg', $arquivo->originalName);
        $this->assertSame(1024, $arquivo->size);
        $this->assertNull($req->file('inexistente'));
    }

    /** Erro de upload precisa estourar, nao chegar mudo no controlador. */
    public function testFileEstouraQuandoOPhpReportouErroNoUpload(): void
    {
        $req = new Request(files: ['doc' => [
            'name' => 'grande.pdf',
            'tmp_name' => '',
            'size' => 0,
            'error' => UPLOAD_ERR_INI_SIZE,
        ]]);

        $this->expectException(StorageException::class);

        $req->file('doc');
    }

    // --- verbo spoofado por formulario HTML ---

    public function testPostComMethodAssumeOVerboDeclarado(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'POST'], post: ['_method' => 'put']);

        $this->assertSame('PUT', $req->method());
        $this->assertTrue($req->isPut());
    }

    public function testGetNaoPodeSerSpoofado(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'GET'], post: ['_method' => 'DELETE']);

        $this->assertSame('GET', $req->method());
    }

    public function testVerboForaDaListaEhIgnorado(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'POST'], post: ['_method' => 'TRACE']);

        $this->assertSame('POST', $req->method());
    }

    // --- protocolo atras de proxy ---

    public function testSchemeIgnoraOProxyQuandoNaoConfiado(): void
    {
        $req = new Request(server: ['HTTP_X_FORWARDED_PROTO' => 'https']);

        $this->assertSame('http', $req->scheme());
    }

    public function testSchemeUsaOProxyQuandoConfiado(): void
    {
        $req = new Request(server: ['HTTP_X_FORWARDED_PROTO' => 'https'], trustProxy: true);

        $this->assertSame('https', $req->scheme());
    }

    /** Cadeia de proxies manda lista; vale o primeiro, que e o do cliente. */
    public function testSchemeLeOPrimeiroDaCadeiaDeProxies(): void
    {
        $req = new Request(server: ['HTTP_X_FORWARDED_PROTO' => 'https, http'], trustProxy: true);

        $this->assertSame('https', $req->scheme());
    }

    public function testHeaderExtraiDoServer(): void
    {
        $req = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer token123']);
        $this->assertSame('Bearer token123', $req->header('Authorization'));
    }

    public function testAuthorizationHeader(): void
    {
        $req = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer abc']);
        $this->assertSame('Bearer abc', $req->authorization());
    }

    public function testPath(): void
    {
        $req = new Request(server: ['REQUEST_URI' => '/app/clientes/editar?id=5']);
        $this->assertSame('/app/clientes/editar', $req->path());
    }

    public function testQueryString(): void
    {
        $req = new Request(server: ['QUERY_STRING' => 'id=5&nome=joao']);
        $this->assertSame('id=5&nome=joao', $req->queryString());
    }

    public function testHost(): void
    {
        $req = new Request(server: ['HTTP_HOST' => 'localhost:8080']);
        $this->assertSame('localhost:8080', $req->host());
    }

    public function testSchemeHttps(): void
    {
        $req = new Request(server: ['HTTPS' => 'on']);
        $this->assertSame('https', $req->scheme());
    }

    public function testSchemeHttp(): void
    {
        $req = new Request(server: ['HTTPS' => 'off']);
        $this->assertSame('http', $req->scheme());
    }

    public function testUrl(): void
    {
        $req = new Request(server: [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/app/clientes'
        ]);
        $this->assertSame('https://example.com/app/clientes', $req->url());
    }

    public function testIp(): void
    {
        $req = new Request(server: ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertSame('192.168.1.1', $req->ip());
    }

    // --- cabecalhos que o PHP nao entrega prefixados ---

    /** Content-Type chega como CONTENT_TYPE, sem o prefixo HTTP_ (heranca do CGI). */
    public function testHeaderLeContentTypeQueNaoVemPrefixado(): void
    {
        $req = new Request(server: ['CONTENT_TYPE' => 'application/json']);

        $this->assertSame('application/json', $req->header('Content-Type'));
    }

    public function testHeaderLeContentLengthQueNaoVemPrefixado(): void
    {
        $req = new Request(server: ['CONTENT_LENGTH' => '42']);

        $this->assertSame('42', $req->header('Content-Length'));
    }

    /** header() nao pode virar porta de entrada para o $_SERVER inteiro. */
    public function testHeaderNaoLeVariavelDeServidorQueNaoEhCabecalho(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'POST', 'DOCUMENT_ROOT' => '/var/www']);

        $this->assertNull($req->header('Request-Method'));
        $this->assertNull($req->header('Document-Root'));
    }

    /** Apache com mod_php nao repassa o Authorization; ele reaparece com REDIRECT_. */
    public function testAuthorizationCaiNoRedirectQuandoOServidorNaoRepassa(): void
    {
        $req = new Request(server: ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer abc123']);

        $this->assertSame('Bearer abc123', $req->authorization());
    }

    public function testAuthorizationPreferOCabecalhoDireto(): void
    {
        $req = new Request(server: [
            'HTTP_AUTHORIZATION' => 'Bearer direto',
            'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect',
        ]);

        $this->assertSame('Bearer direto', $req->authorization());
    }

    // --- atributos: como o middleware entrega dado ao controlador ---

    public function testWithAttributeDevolveCopiaSemMutarAOriginal(): void
    {
        $original = new Request(server: []);

        $comUsuario = $original->withAttribute('usuario', 'joao');

        $this->assertNotSame($original, $comUsuario);
        $this->assertSame('joao', $comUsuario->getAttribute('usuario'));
        $this->assertNull($original->getAttribute('usuario'));
    }

    public function testGetAttributeDevolveODefaultQuandoNaoExiste(): void
    {
        $req = new Request(server: []);

        $this->assertSame('anonimo', $req->getAttribute('usuario', 'anonimo'));
    }

    public function testAttributesAcumulamEntreCopias(): void
    {
        $req = (new Request(server: []))
            ->withAttribute('a', 1)
            ->withAttribute('b', 2);

        $this->assertSame(['a' => 1, 'b' => 2], $req->attributes());
    }
}
