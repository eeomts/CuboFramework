<?php

namespace Cubo\Tests\Support\Views;

use Cubo\View\View;

/**
 * Espelha o que os helpers reais fazem (MenuHelper, ImportHelper, TabHelper):
 * sobrescrevem render() por completo, nao setam template e apenas processam
 * params -- que o template do pai le depois.
 */
class SpyHelperView extends View
{
    /** @var int quantas vezes render() foi chamado */
    public static int $renders = 0;

    public function render(): void
    {
        self::$renders++;

        // o pai deve enxergar este param depois do render
        $this->addParam('vindo_do_filho', 'ok');
    }

    public static function reset(): void
    {
        self::$renders = 0;
    }
}
