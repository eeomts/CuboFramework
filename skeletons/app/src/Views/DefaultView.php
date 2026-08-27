<?php

declare(strict_types=1);

namespace App\Views;

use Cubo\View\View;

final class DefaultView extends View
{
    protected function _setDefaultParams(): void
    {
        $this->setTemplate('layout.php');
    }
}
