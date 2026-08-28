# Migrations

Arquivos `.php` desta pasta sao migrations. O nome comeca com data para que a
ordem alfabetica seja a cronologica:

    2026_08_28_143000_cria_clientes.php

O arquivo DEVOLVE uma instancia anonima -- assim duas migrations podem descrever
a mesma tabela sem colidir nome de classe.

```php
<?php

use Cubo\Database\Migrations\Migration;
use Cubo\Database\Migrations\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('clientes', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->integer('fk_cidade');
            $t->dateTime('data_nascimento')->nullable();
            $t->decimal('mon_limite', 10, 2)->default(0);
            $t->dateTime('created');
            $t->dateTime('updated');
            $t->tinyInteger('deleted')->default(0);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('clientes');
    }
};
```

## Comandos

    cubo migrate            aplica o que falta
    cubo migrate:status     lista o que rodou e o que falta
    cubo migrate:rollback   desfaz o ULTIMO lote

Lote: tudo que sobe numa mesma execucao recebe o mesmo numero, e o rollback
desfaz o lote inteiro -- e assim que se desfaz um deploy.

## A convencao e verificada

O Cubo RECUSA a migration antes de emitir DDL se a tabela sair da convencao:

- toda tabela tem `created`, `updated` e `deleted` (sem `_at` no fim de nada).
  Tabela de vinculo puro dispensa com `exigeColunasDeControle: false`
- chave estrangeira e PREFIXO: `fk_cliente`, nunca `cliente_id`
- data comeca com `data_`, monetario com `mon_`, inteiro com `num_` (ou `fk_`)
- tabela `_aux` e lista fechada no lugar de enum: precisa de `id` e `nome`

Nao e estilo: o `SearchCriteria` escolhe o tipo de filtro pelo prefixo e o
`SoftDeleteFlag` depende do `deleted`. Fora do padrao, o filtro de data viraria
LIKE e ninguem perceberia.

Escotilha de fuga, quando for mesmo necessario:

    $schema->create('legado', $definicao, validarConvencao: false);
