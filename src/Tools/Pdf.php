<?php

namespace Cubo\Tools;

use Cubo\Exceptions\MissingDependencyException;
use Mpdf\Mpdf;

/**
 * Geração de PDF a partir de HTML.
 *
 * O v1 dependia do
 * mPDF 5.6 (pasta MPDF56), que NÃO roda em PHP 8 (usa each()/create_function()).
 * Aqui a engine passa a ser o mpdf/mpdf ^8 (Composer).
 * Veja o GUIA DE MIGRACAO no fim do arquivo.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Pdf
{
    /**
     * Gera um PDF a partir de HTML. (ex-htmlToPdf)
     *
     * @param string $html Conteúdo HTML.
     * @param string|null $filename Nome do arquivo/título do documento.
     * @param string $dest Destino do Output: 'I' inline, 'D' download, 'F' arquivo, 'S' retorna a string do PDF.
     * @param string  $orientation 'P' retrato ou 'L' paisagem.
     * @param string|null $footer Rodapé em HTML (aceita tags do mPDF, ex {PAGENO}).
     * @param string|null $header Cabeçalho em HTML.
     * @param string $margins "left,right,top,bottom" em mm.
     *
     * @return string Bytes do PDF quando $dest = 'S'; string vazia nos demais.
     */
    public static function fromHtml(
        string $html,
        ?string $filename = null,
        string $dest = 'I',
        string $orientation = 'P',
        ?string $footer = null,
        ?string $header = null,
        string $margins = '0,0,5,0',
    ): string {
        $mpdf = self::makeMpdf($orientation, $margins);

        if ($header !== null) {
            $mpdf->SetHTMLHeader($header);
        }
        if ($footer !== null) {
            $mpdf->SetHTMLFooter($footer);
        }
        if ($filename !== null) {
            $mpdf->SetTitle($filename);
        }

        $mpdf->WriteHTML($html);

        return (string) $mpdf->Output($filename ?? '', $dest);
    }

    /**
     * Gera um PDF a partir de HTML e anexa outros PDFs ao final, cada um
     * precedido por uma página "Anexo N". (ex-htmlToPdfAnexos / htmlToPdfAnexos2)
     *
     * Unifica as duas variantes do v1 (uma com PDFMerger, outra com TCPDI) numa
     * só, usando apenas o mpdf 8 (que importa PDFs externos via fpdi embutido).
     *
     * Diferença de contrato: o v1 recebia a estrutura de upload do banco
     * ($anexos[$k] = [['extensao'=>'.pdf','nome'=>...], ...]) e prefixava a
     * constante CORE_CLIENTE_FOLDER."upload/". Aqui recebe uma lista de caminhos
     * já resolvidos - sem acoplamento a constantes, e testável.
     *
     * @param string $html Conteúdo HTML do documento principal.
     * @param list<string> $attachments Caminhos de arquivos PDF a anexar. Itens
     *   inexistentes ou que não sejam .pdf são ignorados.
     * @param string|null $filename Nome do arquivo/título do documento.
     * @param string $dest 'I' inline, 'D' download, 'F' arquivo, 'S' retorna a string.
     * @param string $orientation 'P' retrato ou 'L' paisagem (do documento principal).
     * @param string|null $footer Rodapé em HTML.
     *
     * @return string Bytes do PDF quando $dest = 'S'; string vazia nos demais.
     */
    public static function fromHtmlWithAttachments(
        string $html,
        array $attachments,
        ?string $filename = null,
        string $dest = 'D',
        string $orientation = 'P',
        ?string $footer = null,
    ): string {
        $mpdf = self::makeMpdf($orientation);

        if ($footer !== null) {
            $mpdf->SetHTMLFooter($footer);
        }
        if ($filename !== null) {
            $mpdf->SetTitle($filename);
        }

        $mpdf->WriteHTML($html);

        $numero = 1;
        foreach ($attachments as $path) {
            if (!is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }

            $mpdf->AddPage($orientation);
            $mpdf->WriteHTML('<div style="text-align:center;margin-top:40%;font-size:15px;"><b>Anexo ' . $numero . '</b></div>');

            $pageCount = $mpdf->setSourceFile($path);
            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $mpdf->importPage($page);
                $size = $mpdf->getTemplateSize($template);
                $mpdf->AddPage($size['orientation']);
                $mpdf->useTemplate($template);
            }

            $numero++;
        }

        return (string) $mpdf->Output($filename ?? '', $dest);
    }

    # ------------------------------------------------------------------- PRIVATE

    /**
     * Monta uma instância mpdf com a configuração base (charset, formato,
     * orientação e margens). Fatorado para ser compartilhado por fromHtml() e
     * fromHtmlWithAttachments().
     *
     * @param string $margins "left,right,top,bottom" em mm.
     */
    private static function makeMpdf(string $orientation, string $margins = '0,0,5,0'): Mpdf
    {
        if (!class_exists(Mpdf::class)) {
            throw MissingDependencyException::for('mpdf/mpdf', self::class);
        }

        [$left, $right, $top, $bottom] = array_pad(array_map('intval', explode(',', $margins)), 4, 0);

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'L' ? 'A4-L' : 'A4',
            'orientation' => $orientation,
            'margin_left' => $left,
            'margin_right' => $right,
            'margin_top' => $top,
            'margin_bottom' => $bottom,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Tools (pdf) -> Cubo\Tools\Pdf
 *
 * ENGINE
 *   mPDF 5.6 (include manual) -> mpdf/mpdf ^8 (Composer, fpdi embutido)
 *
 * RENOMEADO
 *   htmlToPdf -> fromHtml($html, $filename, $dest, $orientation, $footer, $header, $margins)
 *
 * UNIFICADOS
 *   htmlToPdfAnexos / htmlToPdfAnexos2
 *     -> fromHtmlWithAttachments($html, $attachments, $filename, $dest, $orientation, $footer)
 *
 * MUDOU
 *   $attachments -- list<string> de caminhos (era estrutura do banco)
 *
 * DESCARTADOS
 *   convertFileBolet X sem substituto
 */
