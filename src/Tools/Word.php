<?php

namespace Cubo\Tools;

/**
 * Geração de documento Word a partir de HTML.
 *  O v1 usava a lib local HTML_TO_DOC, que NÃO gera .docx de verdade: é o truque "HTML como
 * Word" - embrulha o HTML num envelope com namespaces do Word e serve como .doc,
 * que o Word abre. Aqui a técnica foi reimplementada limpa e SEM dependência.
 * Veja o GUIA DE MIGRACAO no fim do arquivo.
 *
 * @package Cubo
 * @author v1: Harish Chauhan (HTML_TO_DOC) / Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Word
{
    /**
     * Gera um documento Word (.doc) a partir de HTML. (ex-htmlToDoc)
     *
     * @param string $html - Conteúdo HTML (pode incluir <head>/<title>/<body>).
     * @param string|null $filename Nome do arquivo (o .doc é acrescentado se faltar).
     * @param string $dest - 'S' retorna a string, 'D' força download, 'F' grava arquivo.
     *
     * @return string O documento gerado (HTML no envelope do Word).
     */
    public static function fromHtml(string $html, ?string $filename = null, string $dest = 'D'): string
    {
        [$head, $body, $title] = self::parse($html);
        $doc = self::envelope($head, $body, $title);
        $file = self::ensureDocExtension($filename ?? 'documento');

        switch (strtoupper($dest)) {
            case 'F':
                file_put_contents($file, $doc);
                break;
            case 'D':
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=\"{$file}\"");
                echo $doc;
                break;
            // 'S' apenas retorna a string
        }

        return $doc;
    }

    # ------------------------------------------------------------------- PRIVATE

    /**
     * Separa <head>, <title> e <body> do HTML de entrada, removendo DOCTYPE e
     * <script>. (ex-HTML_TO_DOC::_parseHtml)
     *
     * @return array{0: string, 1: string, 2: string} [head, body, title]
     */
    private static function parse(string $html): array
    {
        $html = preg_replace('/<!DOCTYPE.*?>/is', '', $html);
        $html = preg_replace('#<script.*?>.*?</script>#is', '', $html);

        $head = '';
        $title = '';

        if (preg_match('#<head>(.*?)</head>#is', $html, $m)) {
            $head = $m[1];

            if (preg_match('#<title>(.*?)</title>#is', $head, $mt)) {
                $title = trim($mt[1]);
            }

            $head = preg_replace('#<title>.*?</title>#is', '', $head);
            $head = preg_replace('#</?head>#is', '', $head);
            $html = preg_replace('#<head>.*?</head>#is', '', $html);
        }

        $body = preg_replace('#</?body[^>]*>#is', '', $html);

        return [trim($head), trim($body), $title !== '' ? $title : 'Documento'];
    }

    private static function ensureDocExtension(string $file): string
    {
        return preg_match('/\.doc$/i', $file) ? $file : $file . '.doc';
    }

    /** Monta o envelope HTML reconhecido pelo Word. */
    private static function envelope(string $head, string $body, string $title): string
    {
        return <<<HTML
        <html xmlns:v="urn:schemas-microsoft-com:vml"
        xmlns:o="urn:schemas-microsoft-com:office:office"
        xmlns:w="urn:schemas-microsoft-com:office:word"
        xmlns="http://www.w3.org/TR/REC-html40">
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="ProgId" content="Word.Document">
        <meta name="Generator" content="Microsoft Word">
        <title>{$title}</title>
        <!--[if gte mso 9]><xml>
         <w:WordDocument><w:View>Print</w:View></w:WordDocument>
        </xml><![endif]-->
        <style>
        p.MsoNormal, li.MsoNormal, div.MsoNormal { margin:0; font-size:10pt; font-family:"Verdana"; }
        @page Section1 { size:8.5in 11.0in; margin:1.0in 1.25in 1.0in 1.25in; }
        div.Section1 { page:Section1; }
        </style>
        {$head}
        </head>
        <body>
        {$body}
        </body>
        </html>
        HTML;
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Tools (word) -> Cubo\Tools\Word
 *
 * RENOMEADO
 *   htmlToDoc -> fromHtml($html, $name, $dest = 'D')
 *
 * DEPENDENCIA REMOVIDA
 *   LIBS/doc/html_to_doc.inc.php X reimplementado inline, sem lib
 *
 * NOTA
 *   Gera HTML-como-Word (.doc), nao .docx OOXML.
 */
