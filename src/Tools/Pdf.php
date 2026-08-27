<?php

namespace Cubo\Tools;

use Cubo\Exceptions\MissingDependencyException;
use Mpdf\Mpdf;

/**
 * Geração de PDF a partir de HTML.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Pdf
{
    /**
     * @param string $dest 'I' inline, 'D' download, 'F' arquivo, 'S' retorna a string
     * @param string $orientation 'P' retrato ou 'L' paisagem
     * @param string $margins "left,right,top,bottom" em mm
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
     * Anexa PDFs ao final, cada um precedido por uma pagina "Anexo N".
     *
     * @param list<string> $attachments caminhos ja resolvidos; item inexistente
     *                                  ou que nao seja .pdf e ignorado
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
     * @param string $margins "left,right,top,bottom" em mm
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
