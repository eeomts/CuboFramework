<?php

namespace Cubo\Tools;

/**
 * Geração de documento Word a partir de HTML.
 *
 * Gera HTML-como-Word (.doc), nao .docx OOXML: o HTML vai num envelope com os
 * namespaces do Word, que o Word abre.
 *
 * @package Cubo
 * @author v1: Harish Chauhan (HTML_TO_DOC) / Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Word
{
    /**
     * @param string $dest 'S' retorna a string, 'D' forca download, 'F' grava arquivo
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
