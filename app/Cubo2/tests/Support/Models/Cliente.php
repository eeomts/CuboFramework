<?php

namespace Cubo\Tests\Support\Models;

use Cubo\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model de teste (fixture). Mostra como fica um model portado: sem hasColumn,
 * so tabela + relacionamento.
 *
 * No v1 este relacionamento era:
 *     $this->hasOne('Cidade', array('local' => 'fk_cidade', 'foreign' => 'id'));
 * como a FK (fk_cidade) e desta tabela, no Eloquent isso e um belongsTo.
 */
class Cliente extends Model
{
    protected $table = 'cliente';

    protected $guarded = [];

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class, 'fk_cidade', 'id');
    }
}
