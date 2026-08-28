<?php

namespace Cubo\Tests\Unit\View;

use Cubo\Exceptions\CuboException;
use Cubo\View\Imports;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Imports::class)]
final class ImportsTest extends TestCase
{
    private string $pasta;

    protected function setUp(): void
    {
        $this->pasta = sys_get_temp_dir() . '/cubo-imports-' . bin2hex(random_bytes(4));
        mkdir($this->pasta . '/assets/css', 0777, true);
        mkdir($this->pasta . '/assets/js', 0777, true);
        file_put_contents($this->pasta . '/assets/css/app.css', '/* css */');
        file_put_contents($this->pasta . '/assets/js/app.js', '// js');
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->pasta, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        @rmdir($this->pasta);
    }

    private function imports(string $html, string $base = '/'): Imports
    {
        $arquivo = $this->pasta . '/imports.html';
        file_put_contents($arquivo, $html);

        return new Imports($arquivo, $this->pasta, $base);
    }

    public function testSeparaOsGruposPelosMarcadores(): void
    {
        $imports = $this->imports(<<<HTML
        <!-- @grupo base -->
        <link rel="stylesheet" href="/assets/css/app.css">
        <!-- @grupo scripts -->
        <script src="/assets/js/app.js"></script>
        HTML);

        $this->assertSame(['base', 'scripts'], $imports->nomes());
    }

    public function testRenderDevolveSoOGrupoPedido(): void
    {
        $imports = $this->imports(<<<HTML
        <!-- @grupo base -->
        <link rel="stylesheet" href="/assets/css/app.css">
        <!-- @grupo scripts -->
        <script src="/assets/js/app.js"></script>
        HTML);

        $saida = $imports->render('scripts');

        $this->assertStringContainsString('app.js', $saida);
        $this->assertStringNotContainsString('app.css', $saida);
    }

    public function testRenderJuntaVariosGruposNaOrdemPedida(): void
    {
        $imports = $this->imports(<<<HTML
        <!-- @grupo base -->
        <link rel="stylesheet" href="/assets/css/app.css">
        <!-- @grupo scripts -->
        <script src="/assets/js/app.js"></script>
        HTML);

        $saida = $imports->render('scripts', 'base');

        $this->assertLessThan(strpos($saida, 'app.css'), strpos($saida, 'app.js'));
    }

    public function testSemGrupoPedidoValeOBase(): void
    {
        $imports = $this->imports(<<<HTML
        <!-- @grupo base -->
        <link rel="stylesheet" href="/assets/css/app.css">
        <!-- @grupo scripts -->
        <script src="/assets/js/app.js"></script>
        HTML);

        $this->assertStringContainsString('app.css', $imports->render());
    }

    /** Arquivo sem marcador nenhum continua funcionando, tudo no grupo padrao. */
    public function testArquivoSemMarcadorCaiTodoNoBase(): void
    {
        $imports = $this->imports('<link rel="stylesheet" href="/assets/css/app.css">');

        $this->assertSame(['base'], $imports->nomes());
        $this->assertStringContainsString('app.css', $imports->render());
    }

    // --- resolucao de caminho e versao ---

    public function testCarimbaAVersaoPeloFilemtime(): void
    {
        $imports = $this->imports('<link rel="stylesheet" href="/assets/css/app.css">');

        $esperado = filemtime($this->pasta . '/assets/css/app.css');

        $this->assertStringContainsString("app.css?v={$esperado}", $imports->render());
    }

    public function testPrefixaASubpastaOndeAAppEstaMontada(): void
    {
        $imports = $this->imports('<link rel="stylesheet" href="/assets/css/app.css">', '/blog/');

        $this->assertStringContainsString('href="/blog/assets/css/app.css?v=', $imports->render());
    }

    /** CDN nao tem filemtime, e mexer no caminho quebraria o integrity da tag. */
    public function testUrlAbsolutaFicaIntacta(): void
    {
        $tag = '<script src="https://cdn.exemplo.com/lib.js" integrity="sha384-x" crossorigin="anonymous"></script>';
        $imports = $this->imports($tag);

        $this->assertStringContainsString('https://cdn.exemplo.com/lib.js"', $imports->render());
        $this->assertStringContainsString('integrity="sha384-x"', $imports->render());
    }

    public function testUrlProtocolRelativeFicaIntacta(): void
    {
        $imports = $this->imports('<script src="//cdn.exemplo.com/lib.js"></script>');

        $this->assertStringContainsString('"//cdn.exemplo.com/lib.js"', $imports->render());
    }

    /** Asset gerado no deploy ainda nao existe: a tag sai sem versao, sem quebrar. */
    public function testArquivoAusenteSaiSemVersao(): void
    {
        $imports = $this->imports('<script src="/assets/js/gerado.js"></script>');

        $saida = $imports->render();

        $this->assertStringContainsString('/assets/js/gerado.js"', $saida);
        $this->assertStringNotContainsString('?v=', $saida);
    }

    public function testPreservaAtributosDaTag(): void
    {
        $imports = $this->imports('<script src="/assets/js/app.js" defer type="module"></script>');

        $saida = $imports->render();

        $this->assertStringContainsString('defer', $saida);
        $this->assertStringContainsString('type="module"', $saida);
    }

    // --- falhas que nao podem ser silenciosas ---

    /** O bug do v1: arquivo sumia e a pagina renderizava sem CSS nem JS, calada. */
    public function testArquivoDeImportsAusenteEstoura(): void
    {
        $imports = new Imports($this->pasta . '/nao-existe.html', $this->pasta);

        $this->expectException(CuboException::class);
        $this->expectExceptionMessageMatches('/nao encontrado/');

        $imports->render();
    }

    /** Typo no nome do grupo deixaria a pagina sem estilo, sem nada acusando. */
    public function testGrupoInexistenteEstouraEListaOsQueExistem(): void
    {
        $imports = $this->imports(<<<HTML
        <!-- @grupo base -->
        <link rel="stylesheet" href="/assets/css/app.css">
        HTML);

        try {
            $imports->render('gird');
            $this->fail('deveria ter estourado');
        } catch (CuboException $e) {
            $this->assertStringContainsString('gird', $e->getMessage());
            $this->assertStringContainsString('base', $e->getMessage());
        }
    }
}
