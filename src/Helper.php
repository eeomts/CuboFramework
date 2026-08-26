<?php

/**
 * Interface para definição de utilização de helpers pelo sistema.
 * 
 * Um Helper pode ser invocado pelo controlador e ser inserido na view, a partir
 * daí os dados processados e os parametros retornados pelo helper estarão disponíveis
 * na view.
 * 
 * @example View::addChild(new Helper());
 * @example View::addChild('Helper');
 * 
 * @package Cubo
 * @author Cristiano
 * 
 *interface Cubo_Helper {
 *  public function render();
 *}
 * 
 */

namespace Cubo;

/**
 * @package Cubo
 * Usa namespace e retorno tipado pra melhor controle, mas mantem a logica orgininal
 * 
 * @author mateus - github.com/eeomts
 * 27/05/26 - 00:26
 * 
 * @return void
 * 
 */
interface Helper

{
    public function render(): void;
}
