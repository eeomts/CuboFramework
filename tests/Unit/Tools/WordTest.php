<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Word;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Word::class)]
final class WordTest extends TestCase
{
    public function testGeraEnvelopeWordComOConteudo(): void
    {
        $doc = Word::fromHtml('<p>Relatório do mês</p>', 'rel', 'S');

        // marca que faz o Word reconhecer o arquivo
        $this->assertStringContainsString('urn:schemas-microsoft-com:office:word', $doc);
        $this->assertStringContainsString('Word.Document', $doc);
        // o corpo passado aparece no documento
        $this->assertStringContainsString('<p>Relatório do mês</p>', $doc);
    }

    public function testExtraiOTituloDoHead(): void
    {
        $html = '<html><head><title>Meu Título</title></head><body><p>corpo</p></body></html>';

        $doc = Word::fromHtml($html, 'x', 'S');

        $this->assertStringContainsString('<title>Meu Título</title>', $doc);
        // o body é desembrulhado (o conteúdo entra, as tags <body> não duplicam no corpo)
        $this->assertStringContainsString('<p>corpo</p>', $doc);
    }

    public function testUsaTituloPadraoQuandoNaoHaTitle(): void
    {
        $doc = Word::fromHtml('<p>sem titulo</p>', 'x', 'S');

        $this->assertStringContainsString('<title>Documento</title>', $doc);
    }

    public function testRemoveDoctypeEScript(): void
    {
        $html = '<!DOCTYPE html><p>ok</p><script>alert(1)</script>';

        $doc = Word::fromHtml($html, 'x', 'S');

        $this->assertStringNotContainsString('<!DOCTYPE', $doc);
        $this->assertStringNotContainsString('<script>', $doc);
        $this->assertStringContainsString('<p>ok</p>', $doc);
    }

    public function testDestinoArquivoGravaComExtensaoDoc(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cubo_word_' . uniqid();
        $file = $base . '.doc';

        try {
            // passa sem extensão; deve gravar com .doc
            Word::fromHtml('<p>arquivo</p>', $base, 'F');

            $this->assertFileExists($file);
            $this->assertStringContainsString('office:word', file_get_contents($file));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
