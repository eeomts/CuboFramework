<?php

/**
 * Gerador de models Eloquent a partir dos models Doctrine 1 do legado.
 *
 * @package Netflex/Cubo - v2 (REFAC 6)
 * @author Mateus - github.com/eeomts
 *
 * COMO FUNCIONA
 * Nao faz parsing de texto (regex em codigo PHP quebra em qualquer formatacao fora
 * do padrao). Em vez disso finge ser o Doctrine: declara um Doctrine_Record falso
 * cujos hasColumn()/hasOne()/hasMany() apenas ANOTAM o que foi chamado. Dai basta
 * dar require no model legado, instanciar e rodar setTableDefinition()/setUp() --
 * o proprio PHP interpreta o arquivo e o espiao coleta o schema real.
 *
 * USO (rodar da pasta app/Cubo2):
 *   php tools/generate-models.php --dry-run     # so mostra o relatorio, nao escreve
 *   php tools/generate-models.php --only=Cliente
 *   php tools/generate-models.php               # gera todos
 *
 * A saida NAO sobrescreve o legado: escreve em app/Netflex/models_v2/.
 * O namespace ja esta registrado no composer.json da RAIZ (feito no REFAC 8):
 *   "autoload": { "psr-4": { "Netflex\\Models\\": "app/Netflex/models_v2/" } }
 */

// ------------------------------------------------------------------ config

const LEGACY_MODELS_DIR = __DIR__ . '/../../Netflex/models';
const OUTPUT_DIR = __DIR__ . '/../../Netflex/models_v2';
const OUTPUT_NAMESPACE = 'Netflex\\Models';

// schema CORE (tabela => conexao + colunas), levantado no banco por check-models.php --write.
// Core = o que existe igual em TODOS os bancos de cliente; divergencia entre clientes e
// customizacao e NAO pode entrar no model base.
const SCHEMA_CORE = __DIR__ . '/schema-core.json';

// colunas que o Cubo\Database\Model ja gerencia: ficam fora do fillable
const MANAGED_COLUMNS = ['id', 'created', 'updated', 'deleted'];

// ------------------------------------------------------- o espiao (o "Doctrine")

/**
 * Substitui o Doctrine_Record. Os models legados chamam estes metodos achando
 * que estao declarando schema; aqui eles so alimentam os arrays de coleta.
 */
abstract class Doctrine_Record
{
    public array $__table = [];
    public array $__columns = [];
    public array $__relations = [];

    public function setTableName(string $name): void
    {
        $this->__table = ['name' => $name];
    }

    public function hasColumn(string $name, string $type = 'string', $length = null, array $options = []): void
    {
        // o Doctrine aceita "coluna as alias"; so a coluna real interessa
        $name = trim(explode(' as ', $name)[0]);

        $this->__columns[$name] = [
            'type' => $type,
            'length' => $length,
            'primary' => (bool) ($options['primary'] ?? false),
            'notnull' => (bool) ($options['notnull'] ?? false),
            'default' => $options['default'] ?? null,
        ];
    }

    public function hasOne(string $target, array $options = []): void
    {
        $this->__relations[] = ['kind' => 'one'] + $this->relation($target, $options);
    }

    public function hasMany(string $target, array $options = []): void
    {
        $this->__relations[] = ['kind' => 'many'] + $this->relation($target, $options);
    }

    private function relation(string $target, array $options): array
    {
        // "Cidade as Municipio" -> classe Cidade, apelido Municipio
        $parts = explode(' as ', $target);

        return [
            'class' => trim($parts[0]),
            'alias' => trim($parts[1] ?? $parts[0]),
            'local' => $options['local'] ?? null,
            'foreign' => $options['foreign'] ?? null,
            // refClass = many-to-many: 'local' e 'foreign' sao colunas da tabela
            // PIVO (a classe do refClass), nao desta tabela.
            'refClass' => $options['refClass'] ?? null,
            'pivotTable' => null,
        ];
    }

    /** engole o resto da API do Doctrine (actAs, index, option, setSubclasses...) */
    public function __call(string $name, array $args) {}

    public static function __callStatic(string $name, array $args) {}
}

/** o legado tem `abstract class Cubo_Model extends Doctrine_Record` */
abstract class Cubo_Model extends Doctrine_Record {}

// ------------------------------------------------------------------ argumentos

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$only = null;
$worker = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, strlen('--only='));
    }
    if (str_starts_with($arg, '--worker=')) {
        $worker = substr($arg, strlen('--worker='));
    }
}

// ---------------------------------------------------------------- modo worker

/**
 * Carrega UM model e imprime o schema coletado em JSON.
 *
 * Roda em processo separado de proposito: ha model legado que nao COMPILA
 * (o Empresa.php declara getEmailConfirmado() duas vezes). Isso e fatal error,
 * que try/catch nao pega -- no mesmo processo, um model podre derrubaria a
 * geracao dos 247. Isolado, ele morre sozinho e vira uma linha do relatorio.
 */
if ($worker !== null) {
    // ha model que estende outro model; o autoloader resolve pela pasta do legado
    spl_autoload_register(function (string $class): void {
        $path = LEGACY_MODELS_DIR . '/' . $class . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });

    $declared = declaredClass($worker);

    if ($declared === null) {
        fwrite(STDERR, 'nenhuma classe encontrada no arquivo');
        exit(1);
    }

    require_once $worker;

    if (!class_exists($declared, false)) {
        fwrite(STDERR, 'o require nao definiu a classe esperada');
        exit(1);
    }

    $spy = new $declared();

    if (!$spy instanceof Doctrine_Record) {
        fwrite(STDERR, 'nao estende Cubo_Model/Doctrine_Record');
        exit(1);
    }

    // e aqui que o model "declara" o schema para o espiao
    if (method_exists($spy, 'setTableDefinition')) {
        $spy->setTableDefinition();
    }
    if (method_exists($spy, 'setUp')) {
        $spy->setUp();
    }

    // many-to-many: descobre o nome da tabela pivo carregando a classe do refClass
    $relations = array_map(function (array $relation): array {
        if ($relation['refClass'] !== null) {
            $relation['pivotTable'] = pivotTable($relation['refClass']);
        }

        return $relation;
    }, $spy->__relations);

    echo json_encode([
        'class' => $declared,
        'table' => $spy->__table['name'] ?? null,
        'columns' => $spy->__columns,
        'relations' => $relations,
    ]);

    exit(0);
}

// -------------------------------------------------------------------- coleta

$files = glob(LEGACY_MODELS_DIR . '/*.php') ?: [];

if ($files === []) {
    fwrite(STDERR, 'Nenhum model em ' . realpath(LEGACY_MODELS_DIR) . PHP_EOL);
    exit(1);
}

$generated = 0;
$skipped = [];
$warnings = [];
$seen = [];

if (!$dryRun && !is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0777, true);
}

foreach ($files as $file) {
    $fileName = basename($file, '.php');

    if ($only !== null && $fileName !== $only) {
        continue;
    }

    $spec = collect($file);

    if (isset($spec['error'])) {
        $skipped[$fileName] = $spec['error'];
        continue;
    }

    $declared = $spec['class'];
    $columns = $spec['columns'];
    $relations = $spec['relations'];

    if ($columns === []) {
        $skipped[$fileName] = 'nenhuma coluna declarada';
        continue;
    }

    // dois arquivos declarando a mesma classe: o segundo sobrescreveria o gerado
    if (isset($seen[$declared])) {
        $skipped[$fileName] = "declara {$declared}, que ja veio de {$seen[$declared]}.php (duplicado)";
        continue;
    }
    $seen[$declared] = $fileName;

    $table = $spec['table'] ?? snake($declared);

    if ($spec['table'] === null) {
        $warnings[] = "{$declared}: sem setTableName(); assumindo tabela '{$table}'";
    }

    // relacionamento apontando para coluna que nao existe = quebrado no legado.
    // (num many-to-many o 'local' e coluna da PIVO, entao nao se aplica)
    foreach ($relations as $relation) {
        $local = $relation['local'];

        if (($relation['refClass'] ?? null) !== null) {
            if ($relation['pivotTable'] === null) {
                $warnings[] = "{$declared}: relacao {$relation['alias']} e many-to-many, mas a pivo {$relation['refClass']} nao foi encontrada; confira o nome da tabela";
            }
            continue;
        }

        if ($local !== null && $local !== 'id' && !isset($columns[$local])) {
            $warnings[] = "{$declared}: relacao {$relation['alias']} usa a coluna '{$local}', que a tabela nao tem (quebrada no legado; gerada mesmo assim)";
        }
    }

    $code = render($declared, $table, $columns, $relations);

    if ($dryRun) {
        // com --only, mostra o resultado para conferencia antes de gerar de verdade
        if ($only !== null) {
            echo PHP_EOL . $code;
        }
    } else {
        file_put_contents(OUTPUT_DIR . '/' . $declared . '.php', $code);
    }

    $generated++;
}

// ----------------------------------------------------------------- relatorio

echo PHP_EOL;
echo $dryRun ? "DRY-RUN (nada foi escrito)" : 'Gerados em ' . OUTPUT_DIR;
echo PHP_EOL . str_repeat('-', 70) . PHP_EOL;
echo "models gerados : {$generated}" . PHP_EOL;
echo 'ignorados      : ' . count($skipped) . PHP_EOL;
echo 'avisos         : ' . count($warnings) . PHP_EOL;

if ($skipped !== []) {
    echo PHP_EOL . "IGNORADOS" . PHP_EOL;
    foreach ($skipped as $name => $reason) {
        echo "  - {$name}: {$reason}" . PHP_EOL;
    }
}

if ($warnings !== []) {
    echo PHP_EOL . "AVISOS (revisar a mao)" . PHP_EOL;
    foreach ($warnings as $warning) {
        echo "  - {$warning}" . PHP_EOL;
    }
}

echo PHP_EOL;
exit(0);

// ------------------------------------------------------------------ funcoes

/**
 * Nome da tabela pivo de um many-to-many (a classe do refClass do Doctrine).
 * So roda dentro do worker, onde o autoloader do legado esta registrado.
 */
function pivotTable(string $refClass): ?string
{
    try {
        if (!class_exists($refClass)) {
            return null;
        }

        $ref = new $refClass();

        if (!$ref instanceof Doctrine_Record) {
            return null;
        }

        if (method_exists($ref, 'setTableDefinition')) {
            $ref->setTableDefinition();
        }

        return $ref->__table['name'] ?? snake($refClass);
    } catch (Throwable) {
        return null;
    }
}

/**
 * Roda o worker para UM model e devolve o schema coletado.
 * Se o processo morrer (model que nao compila), devolve ['error' => motivo].
 *
 * @return array{class:string,table:?string,columns:array,relations:array}|array{error:string}
 */
function collect(string $file): array
{
    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__FILE__)
        . ' --worker=' . escapeshellarg($file);

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
        return ['error' => 'nao foi possivel iniciar o worker'];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exit = proc_close($process);

    if ($exit !== 0) {
        return ['error' => firstLine($stderr) ?: "worker saiu com codigo {$exit}"];
    }

    $spec = json_decode($stdout, true);

    if (!is_array($spec)) {
        return ['error' => 'saida do worker ilegivel: ' . firstLine($stdout)];
    }

    return $spec;
}

/** Primeira linha util de uma mensagem de erro (fatal error do PHP e verboso). */
function firstLine(string $text): string
{
    foreach (explode("\n", trim($text)) as $line) {
        $line = trim($line);
        if ($line !== '') {
            return $line;
        }
    }

    return '';
}

/** Nome da classe declarada no arquivo, sem executar nada (usa o tokenizer). */
function declaredClass(string $file): ?string
{
    $tokens = token_get_all((string) file_get_contents($file));

    foreach ($tokens as $i => $token) {
        if (is_array($token) && $token[0] === T_CLASS) {
            // pula espacos ate o nome
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    return $tokens[$j][1];
                }
            }
        }
    }

    return null;
}

/** Monta o codigo do model Eloquent. */
function render(string $class, string $table, array $columns, array $relations): string
{
    $ns = OUTPUT_NAMESPACE;

    /*
     * O model base so pode conter o que e CORE (existe igual em todos os clientes).
     * Coluna que so alguns clientes tem e customizacao: mora na pasta do cliente,
     * nao aqui. Sem o schema levantado (core === null), nao filtra nada.
     */
    $core = coreColumns($table);
    $descartadas = [];

    $fillable = array_values(array_filter(
        array_keys($columns),
        function (string $c) use ($columns, $core, &$descartadas): bool {
            if (in_array($c, MANAGED_COLUMNS, true) || ($columns[$c]['primary'] ?? false)) {
                return false;
            }

            if ($core !== null && !isset($core[$c])) {
                $descartadas[] = $c; // custom de algum cliente, ou coluna que nao existe mais
                return false;
            }

            return true;
        }
    ));

    $casts = array_intersect_key(casts($columns), array_flip($fillable));
    $methods = relationMethods($relations, $class, $core);

    $uses = ["use Cubo\\Database\\Model;"];
    if (str_contains($methods, ': BelongsTo')) {
        $uses[] = 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;';
    }
    if (str_contains($methods, ': HasOne')) {
        $uses[] = 'use Illuminate\\Database\\Eloquent\\Relations\\HasOne;';
    }
    if (str_contains($methods, ': HasMany')) {
        $uses[] = 'use Illuminate\\Database\\Eloquent\\Relations\\HasMany;';
    }
    if (str_contains($methods, ': BelongsToMany')) {
        $uses[] = 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany;';
    }
    sort($uses);

    $code = "<?php\n\n";
    $code .= "namespace {$ns};\n\n";
    $code .= implode("\n", $uses) . "\n\n";
    $code .= "/**\n";
    $code .= " * Gerado por tools/generate-models.php a partir do model Doctrine 1 do legado.\n";
    $code .= " * O schema (hasColumn) foi descartado: o Eloquent le as colunas da tabela.\n";

    if ($descartadas !== []) {
        $code .= " *\n";
        $code .= " * Fora do fillable por NAO serem core (nao existem em todos os clientes):\n";
        $code .= " * " . implode(', ', $descartadas) . "\n";
        $code .= " * Se alguma for customizacao de um cliente, o lugar dela e clientes/<id>/models/.\n";
    }

    $code .= " */\n";
    $code .= "class {$class} extends Model\n{\n";

    /*
     * Multi-tenant: 'contas' e o banco central, 'cliente' e o banco do tenant.
     * No v1 o model NAO sabia em qual banco morava -- dependia de alguem ter chamado
     * changeConnection() antes, e quem esquecia lia a tabela no banco errado.
     * Declarando a conexao aqui, o roteamento vira responsabilidade do proprio model.
     * O mapa vem do banco de verdade: tools/check-models.php --map
     */
    $connection = connectionOf($table);

    if ($connection !== null) {
        $code .= "    protected \$connection = '{$connection}';\n\n";
    }

    $code .= "    protected \$table = '{$table}';\n\n";
    $code .= "    protected \$fillable = [\n";
    foreach ($fillable as $column) {
        $code .= "        '{$column}',\n";
    }
    $code .= "    ];\n";

    if ($casts !== []) {
        $code .= "\n    protected \$casts = [\n";
        foreach ($casts as $column => $cast) {
            $code .= "        '{$column}' => '{$cast}',\n";
        }
        $code .= "    ];\n";
    }

    if ($methods !== '') {
        $code .= $methods;
    }

    $code .= "}\n";

    return $code;
}

/**
 * Schema core levantado no banco (tabela => {connection, columns}).
 * Se ainda nao foi gerado, devolve [] e o gerador se comporta como antes:
 * sem $connection e com o fillable inteiro que o Doctrine declarava.
 */
function coreSchema(): array
{
    static $schema = null;

    if ($schema === null) {
        $schema = is_file(SCHEMA_CORE)
            ? (array) json_decode((string) file_get_contents(SCHEMA_CORE), true)
            : [];
    }

    return $schema;
}

/** Conexao onde a tabela mora ('contas' ou 'cliente'). */
function connectionOf(string $table): ?string
{
    return coreSchema()[$table]['connection'] ?? null;
}

/**
 * Colunas CORE da tabela (as que existem em todos os clientes).
 * null = tabela desconhecida (schema ainda nao levantado, ou tabela custom/morta):
 * nesse caso nao da para filtrar nada, e o gerador nao filtra.
 *
 * @return array<string,true>|null
 */
function coreColumns(string $table): ?array
{
    $columns = coreSchema()[$table]['columns'] ?? null;

    return $columns === null ? null : array_fill_keys($columns, true);
}

/** Tipo Doctrine -> cast do Eloquent. So emite cast onde ha ganho real. */
function casts(array $columns): array
{
    $casts = [];

    foreach ($columns as $name => $spec) {
        if (in_array($name, ['id', 'created', 'updated'], true)) {
            continue; // PK e timestamps o Eloquent ja trata
        }

        $cast = match ($spec['type']) {
            // integer(1) NAO vira boolean: no legado essas colunas guardam 0/1/2
            // (o proprio prepareSearch tem a regra "pago == 2"). Cast para boolean
            // leria 2 como true e regravaria 1, corrompendo o dado.
            'integer' => 'integer',
            'boolean' => 'boolean',
            'float', 'double' => 'float',
            'decimal' => 'decimal:2',
            'timestamp', 'datetime' => 'datetime',
            'date' => 'date',
            'time' => 'string',
            default => null,
        };

        // `deleted` e integer(1), mas e FLAG do soft delete: cast para boolean
        // quebraria o NotDeletedScope, que compara com 1. Fica sem cast.
        if ($name === 'deleted') {
            continue;
        }

        if ($cast !== null) {
            $casts[$name] = $cast;
        }
    }

    return $casts;
}

/**
 * Relacionamentos.
 *
 * O hasOne do Doctrine 1 era ambiguo: servia para os dois lados, dependendo de
 * quem era o 'local'. A traducao:
 *   local = coluna FK desta tabela -> belongsTo  (a chave e minha)
 *   local = id (minha PK)          -> hasOne     (a chave e do outro)
 */
function relationMethods(array $relations, string $self, ?array $core = null): string
{
    if ($relations === []) {
        return '';
    }

    $code = '';
    $used = [];

    foreach ($relations as $relation) {
        $target = $relation['class'];
        $local = $relation['local'];
        $foreign = $relation['foreign'];

        if ($local === null || $foreign === null) {
            continue; // sem as chaves nao da para inferir com seguranca
        }

        /*
         * belongsTo: a chave local mora NESTA tabela. Se a coluna nao existe no banco
         * (conferido em contas + todos os clientes), a relacao estava morta ja no v1 --
         * chamar ela dava erro de SQL. Sai comentada em vez de apagada: o legado fica
         * rastreavel e a poda definitiva e sua, contra o banco de producao.
         */
        $ehBelongsTo = ($relation['refClass'] ?? null) === null
            && $relation['kind'] !== 'many'
            && $local !== 'id';

        if ($ehBelongsTo && $core !== null && !isset($core[$local])) {
            $name = lcfirst($relation['alias']);
            $code .= "\n    // MORTA no banco: '{$local}' nao existe nesta tabela (nem em contas, nem em nenhum cliente).\n";
            $code .= "    // public function {$name}(): BelongsTo\n";
            $code .= "    // {\n";
            $code .= "    //     return \$this->belongsTo({$target}::class, '{$local}', '{$foreign}');\n";
            $code .= "    // }\n";
            continue;
        }

        $name = lcfirst($relation['alias']);

        // evita dois metodos com o mesmo nome no mesmo model
        $base = $name;
        $n = 2;
        while (isset($used[$name])) {
            $name = $base . $n++;
        }
        $used[$name] = true;

        // many-to-many via tabela pivo (refClass do Doctrine)
        if (($relation['refClass'] ?? null) !== null) {
            $pivot = $relation['pivotTable'] ?? snake($relation['refClass']);
            $code .= method(
                $name,
                'BelongsToMany',
                "belongsToMany({$target}::class, '{$pivot}', '{$local}', '{$foreign}')"
            );
            continue;
        }

        if ($relation['kind'] === 'many') {
            $code .= method($name, 'HasMany', "hasMany({$target}::class, '{$foreign}', '{$local}')");
            continue;
        }

        if ($local === 'id') {
            // a FK mora na outra tabela
            $code .= method($name, 'HasOne', "hasOne({$target}::class, '{$foreign}', '{$local}')");
            continue;
        }

        // a FK mora aqui
        $code .= method($name, 'BelongsTo', "belongsTo({$target}::class, '{$local}', '{$foreign}')");
    }

    return $code;
}

function method(string $name, string $return, string $body): string
{
    return "\n    public function {$name}(): {$return}\n"
        . "    {\n"
        . "        return \$this->{$body};\n"
        . "    }\n";
}

function snake(string $value): string
{
    return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
}
