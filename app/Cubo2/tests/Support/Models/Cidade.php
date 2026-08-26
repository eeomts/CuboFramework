<?php

namespace Cubo\Tests\Support\Models;

use Cubo\Database\Model;

/**
 * Model de teste (fixture). Nao e o model real da aplicacao -- existe so para
 * exercitar a base (Cubo\Database\Model) contra o sqlite em memoria.
 */
class Cidade extends Model
{
    protected $table = 'cidade';

    protected $guarded = [];
}
