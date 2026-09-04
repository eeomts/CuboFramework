<?php

/**
 * Interface para definição de utilização de helpers pelo sistema.
 *
 * Um Helper pode ser invocado pelo controlador e ser inserido na view; dai os
 * dados processados e os parametros que ele devolve ficam disponiveis na view.
 *
 * @example View::addChild(new Helper());
 * @example View::addChild('Helper');
 *
 * @package Cubo
 * @author Cristiano
 */

namespace Cubo;

/**
 * @package Cubo
 * @author mateus - github.com/eeomts
 * 27/05/26 - 00:26
 */
interface Helper
{
    public function render(): void;
}
