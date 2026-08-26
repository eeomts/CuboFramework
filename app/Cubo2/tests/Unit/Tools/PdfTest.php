<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Pdf;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Pdf::class)]
final class PdfTest extends TestCase
{
    public function testGeraPdfValidoComoString(): void
    {
        $pdf = Pdf::fromHtml('<h1>Relatório</h1>', 'rel', 'S');

        // todo PDF começa com a assinatura %PDF
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function testAceitaConteudoAcentuadoUtf8(): void
    {
        // regressão: o v1 dependia de conversão de charset manual; aqui é utf-8 nativo
        $pdf = Pdf::fromHtml('<p>ação, coração, José</p>', 'acentos', 'S');

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testOrientacaoPaisagemGeraPdf(): void
    {
        $pdf = Pdf::fromHtml('<p>paisagem</p>', 'land', 'S', 'L');

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testHeaderEFooterNaoQuebram(): void
    {
        $pdf = Pdf::fromHtml(
            '<p>corpo</p>',
            'doc',
            'S',
            'P',
            '<div>rodapé {PAGENO}/{nbpg}</div>',
            '<div>cabeçalho</div>',
        );

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testDestinoArquivoEscreveNoDisco(): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cubo_pdf_' . uniqid() . '.pdf';

        try {
            Pdf::fromHtml('<p>arquivo</p>', $file, 'F');

            $this->assertFileExists($file);
            $this->assertStringStartsWith('%PDF', file_get_contents($file));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testAnexaPdfExistenteGeraPdfValido(): void
    {
        // o fixture do anexo e gerado pela propria classe -> PDF real em disco
        $anexo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cubo_anexo_' . uniqid() . '.pdf';
        Pdf::fromHtml('<p>conteudo do anexo</p>', $anexo, 'F');

        try {
            $semAnexo = Pdf::fromHtml('<p>documento principal</p>', 'doc', 'S');
            $comAnexo = Pdf::fromHtmlWithAttachments('<p>documento principal</p>', [$anexo], 'doc', 'S');

            $this->assertStringStartsWith('%PDF', $comAnexo);
            // as paginas do anexo entraram -> saida maior que o mesmo doc sem anexo
            $this->assertGreaterThan(strlen($semAnexo), strlen($comAnexo));
        } finally {
            if (is_file($anexo)) {
                unlink($anexo);
            }
        }
    }

    public function testIgnoraAnexosInexistentesOuNaoPdf(): void
    {
        $naoPdf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cubo_nao_pdf_' . uniqid() . '.txt';
        file_put_contents($naoPdf, 'isto nao e um pdf');

        try {
            $pdf = Pdf::fromHtmlWithAttachments(
                '<p>principal</p>',
                ['/caminho/que/nao/existe.pdf', $naoPdf],
                'doc',
                'S',
            );

            // ambos os anexos invalidos sao ignorados, sem estourar excecao
            $this->assertStringStartsWith('%PDF', $pdf);
        } finally {
            if (is_file($naoPdf)) {
                unlink($naoPdf);
            }
        }
    }
}
