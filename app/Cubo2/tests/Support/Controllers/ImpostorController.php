<?php

namespace Cubo\Tests\Support\Controllers;

/**
 * Classe com nome de controlador que NAO e um Cubo\Controller.
 *
 * Existe para provar o endurecimento do kernel: no v1, controlador vindo da URL
 * era instanciado com `new $control(...)` sem checagem, entao qualquer classe
 * terminada em "Controller" alcancavel pelo autoload podia ser instanciada por
 * quem montasse a URL.
 */
class ImpostorController
{
}
