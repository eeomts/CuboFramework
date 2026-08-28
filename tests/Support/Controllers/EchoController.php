<?php

namespace Cubo\Tests\Support\Controllers;

use Cubo\Controller;

/**
 * Controlador que escreve direto na saida, como uma View faz ao dar include no
 * template. Serve para provar que o kernel captura esse corpo na Response.
 */
class EchoController extends Controller
{
    public const MARCADOR = '<p>corpo-vindo-do-controller</p>';

    public function display(): void
    {
        echo self::MARCADOR;
        echo '<i>' . (string) $this->_request->getAttribute('usuario', 'anonimo') . '</i>';

        parent::display();
    }
}
