<?php

namespace Cubo\Tests\Support\Views;

use Cubo\View\View;

/**
 * View que so anota que foi renderizada, sem precisar de template em disco.
 * Serve para provar QUAL view o kernel renderizou (a do core ou a do modulo).
 */
class RecordingView extends View
{
    public int $renders = 0;

    public function render(): void
    {
        $this->renders++;
    }
}
