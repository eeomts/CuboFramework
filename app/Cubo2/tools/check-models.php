<?php

/**
 * Analisa o schema REAL dos bancos e separa o que e CORE do que e CUSTOMIZACAO.
 *
 * @package Netflex/Cubo - v2 (REFAC 6)
 * @author Mateus - github.com/eeomts
 *
 * O MODELO DE NEGOCIO DO NETFLEX (e por que isso importa aqui)
 * O sistema e multi-tenant com dois planos:
 *   - netflex_contas   = plano de CONTROLE: quem sao os clientes, onde fica o banco de
 *                        cada um, o que cada um contratou (plano/modulo/resource),
 *                        menu/nivel/permissao e dominio comum (uf, cidade).
 *   - netflex_v8_<id>  = plano de DADOS: o banco daquele cliente.
 * Quando um cliente contrata uma feature que nao existe no produto, ela NAO vai para o
 * core: vira codigo na pasta do cliente (clientes/<id>/) e tabela/coluna so no banco
 * dele. Ou seja: DIVERGENCIA ENTRE OS BANCOS DE CLIENTE == CUSTOMIZACAO.
 *
 * Daí o criterio deste script:
 *   CORE   = o que existe IGUAL em TODOS os bancos de cliente (ou no banco de contas)
 *   CUSTOM = qualquer divergencia entre os bancos de cliente
 * O model base so pode conter o que e core -- senao ele mente para 90% dos clientes.
 *
 * SAIDA
 *   tools/schema-core.json   -> tabela => {connection, columns}. O generate-models.php
 *                               le isso para escrever $connection e filtrar o $fillable.
 *   tools/custom-report.md   -> o que e customizacao, por cliente (para porte futuro).
 *
 * USO (da pasta app/Cubo2, com o MySQL no ar):
 *   php tools/check-models.php            # analisa e relata
 *   php tools/check-models.php --write    # + grava schema-core.json e custom-report.md
 *
 * RESSALVA: a intersecao e calculada sobre os tenants DESTA maquina. Se producao tiver
 * um cliente com schema mais enxuto, algo que aqui parece core pode ser custom la.
 */

// vendor/ mora na RAIZ do repo desde o REFAC 8 (um projeto Composer, um autoloader)
require __DIR__ . '/../../../vendor/autoload.php';

use Cubo\Database\Db;
use Cubo\Tools\Str;

const MODELS_DIR = __DIR__ . '/../../Netflex/models_v2';
const MODELS_NAMESPACE = 'Netflex\\Models';
const APP_CONFIG_INI = __DIR__ . '/../../Netflex/config/config.ini';
const SCHEMA_FILE = __DIR__ . '/schema-core.json';
const CUSTOM_REPORT = __DIR__ . '/custom-report.md';

// nomes das conexoes, como o legado ja as chamava (Auth.php / ModulosController)
const CONN_CONTAS = 'contas';
const CONN_CLIENTE = 'cliente';

$write = in_array('--write', array_slice($argv, 1), true);

// -------------------------------------------------------------------- conexoes

/*
 * Config::initializeConfig() nao serve aqui: ele le $_SERVER['HTTP_HOST'] e REQUEST_URI,
 * que nao existem em CLI. (Debito do REFAC 8: separar o carregamento do ini da
 * inicializacao das constantes de request.)
 */
$ini = parse_ini_file(APP_CONFIG_INI, true);

if ($ini === false) {
    fwrite(STDERR, 'nao consegui ler ' . APP_CONFIG_INI . PHP_EOL);
    exit(1);
}

$conf = $ini['database.' . $ini['cubo']['location']];
$db = Db::getInstance();

$credentials = fn(string $database): array => [
    'driver' => $conf['dbtype'] ?? 'mysql',
    'host' => $conf['host'],
    'port' => $conf['port'] ?? 3306,
    'database' => $database,
    'username' => Str::cuboDecode($conf['user']),
    'password' => Str::cuboDecode($conf['pass']),
    'charset' => 'utf8',
    'prefix' => $ini['cubo']['table_prefix'] ?: '',
];

$db->addConnection(CONN_CONTAS, $credentials($conf['db']));

try {
    $db->getPdo();
} catch (Throwable $e) {
    fwrite(STDERR, 'nao conectou no banco: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$tenants = allTenants($db, $ini['cubo']['database_prefix'] ?: 'netflex_v8_');

if ($tenants === []) {
    fwrite(STDERR, 'nenhum banco de cliente encontrado (prefixo database_prefix)' . PHP_EOL);
    exit(1);
}

echo 'contas  : ' . $conf['db'] . PHP_EOL;
echo 'clientes: ' . implode(', ', $tenants) . PHP_EOL;

// --------------------------------------------------------------------- coleta

$contas = schemaOf($db, CONN_CONTAS, $conf['db']);

/** @var array<string,array<string,array<string,string>>> $porTenant tenant => tabela => coluna => tipo */
$porTenant = [];

foreach ($tenants as $tenant) {
    $db->addConnection(CONN_CLIENTE, $credentials($tenant));
    $porTenant[$tenant] = schemaOf($db, CONN_CLIENTE, $tenant);
}

$db->addConnection(CONN_CLIENTE, $credentials($tenants[0]));

echo 'tabelas em contas: ' . count($contas) . PHP_EOL;

// ------------------------------------------------- core x custom (a intersecao)

$total = count($tenants);

// em quantos tenants cada tabela aparece
$tabelaEm = [];
foreach ($porTenant as $tenant => $schema) {
    foreach ($schema as $table => $_) {
        $tabelaEm[$table][] = $tenant;
    }
}

$coreCliente = [];   // tabela => colunas presentes em TODOS os tenants
$customTabela = [];  // tabela => tenants que a tem (mas nao todos)
$customColuna = [];  // tenant => [ "tabela.coluna" ]
$tipoDivergente = []; // "tabela.coluna" => [tenant => tipo]

foreach ($tabelaEm as $table => $tenantsComTabela) {
    if (count($tenantsComTabela) < $total) {
        // tabela que so alguns clientes tem = customizacao daquele(s) cliente(s)
        $customTabela[$table] = $tenantsComTabela;
        continue;
    }

    // tabela em todos: agora a coluna precisa estar em todos tambem
    $colunasDeTodos = null;

    foreach ($tenantsComTabela as $tenant) {
        $colunas = $porTenant[$tenant][$table];

        $colunasDeTodos = $colunasDeTodos === null
            ? $colunas
            : array_intersect_key($colunasDeTodos, $colunas);
    }

    $coreCliente[$table] = array_keys($colunasDeTodos ?? []);

    // as que sobraram em cada tenant sao colunas customizadas dele
    $tipos = [];

    foreach ($tenantsComTabela as $tenant) {
        foreach ($porTenant[$tenant][$table] as $column => $type) {
            if (!isset($colunasDeTodos[$column])) {
                $customColuna[$tenant][] = "{$table}.{$column}";
                continue;
            }

            $tipos[$column][$tenant] = $type;
        }
    }

    // mesma coluna, tipo diferente entre clientes: fica no core (existe em todos),
    // mas e sinal de schema fora de sincronia -- vale conferir a mao
    foreach ($tipos as $column => $porCliente) {
        if (count(array_unique($porCliente)) > 1) {
            $tipoDivergente["{$table}.{$column}"] = $porCliente;
        }
    }
}

// ------------------------------------------------------------------- o mapa core

$schema = [];

foreach ($contas as $table => $columns) {
    $schema[$table] = ['connection' => CONN_CONTAS, 'columns' => array_keys($columns)];
}

// tabela nos DOIS bancos -> vence o cliente (decisao do usuario: o modulo roda no tenant)
foreach ($coreCliente as $table => $columns) {
    $schema[$table] = ['connection' => CONN_CLIENTE, 'columns' => $columns];
}

// ------------------------------------------------------------------ conferencia

foreach (glob(MODELS_DIR . '/*.php') ?: [] as $file) {
    require_once $file;
}

$semTabela = [];
$modelCustom = [];
$porConexao = [CONN_CONTAS => 0, CONN_CLIENTE => 0];

foreach (glob(MODELS_DIR . '/*.php') ?: [] as $file) {
    $name = basename($file, '.php');
    $class = MODELS_NAMESPACE . '\\' . $name;

    if (!class_exists($class)) {
        continue;
    }

    $table = (new $class())->getTable();

    if (isset($customTabela[$table])) {
        $modelCustom[] = "{$name} ({$table}): so em " . implode(', ', $customTabela[$table]);
        continue;
    }

    if (!isset($schema[$table])) {
        $semTabela[] = "{$name} -> tabela '{$table}' nao existe em contas nem em nenhum cliente";
        continue;
    }

    $porConexao[$schema[$table]['connection']]++;
}

// --------------------------------------------------------------------- relatorio

section('CORE (vai para o model base)', [
    'tabelas em contas : ' . count($contas),
    'tabelas core do cliente (em TODOS os ' . $total . ' clientes): ' . count($coreCliente),
    '',
    'models roteados para "' . CONN_CONTAS . '": ' . $porConexao[CONN_CONTAS],
    'models roteados para "' . CONN_CLIENTE . '": ' . $porConexao[CONN_CLIENTE],
]);

section('CUSTOM: TABELA so em alguns clientes (' . count($customTabela) . ')', array_map(
    fn(string $t) => "{$t}: " . implode(', ', $customTabela[$t]),
    array_slice(array_keys($customTabela), 0, 15)
));

section('CUSTOM: COLUNA extra em tabela core', array_map(
    fn(string $tenant) => "{$tenant}: " . count($customColuna[$tenant]) . ' colunas',
    array_keys($customColuna)
));

section('TIPO DIVERGENTE entre clientes (mesma coluna, tipo diferente)', array_map(
    fn(string $col) => "{$col}: " . json_encode($tipoDivergente[$col]),
    array_slice(array_keys($tipoDivergente), 0, 15)
));

section('MODEL de tabela CUSTOM (nao entra no core)', $modelCustom);
section('MODEL sem tabela em lugar nenhum (morto?)', $semTabela);

// ------------------------------------------------------------------- gravacao

if ($write) {
    ksort($schema);
    file_put_contents(SCHEMA_FILE, json_encode($schema, JSON_PRETTY_PRINT));

    file_put_contents(CUSTOM_REPORT, customReport($customTabela, $customColuna, $tipoDivergente, $tenants));

    echo PHP_EOL . 'gravado: ' . SCHEMA_FILE . ' (' . count($schema) . ' tabelas core)' . PHP_EOL;
    echo 'gravado: ' . CUSTOM_REPORT . PHP_EOL;
    echo 'agora rode `php tools/generate-models.php` para regerar os models com $connection e fillable core' . PHP_EOL;
}

echo PHP_EOL;
exit(0);

// ---------------------------------------------------------------------- funcoes

/** @return list<string> */
function allTenants(Db $db, string $prefix): array
{
    $bancos = $db->executeSql('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_filter(
        array_map('strval', $bancos),
        fn(string $banco) => str_starts_with($banco, $prefix)
    ));
}

/**
 * Tabelas, colunas e tipos de um banco.
 *
 * @return array<string,array<string,string>> tabela => coluna => tipo
 */
function schemaOf(Db $db, string $connection, string $database): array
{
    $db->changeConnection($connection);

    $rows = $db->executeSql(
        'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?',
        [$database]
    )->fetchAll(PDO::FETCH_ASSOC);

    $schema = [];

    foreach ($rows as $row) {
        $schema[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
    }

    return $schema;
}

function customReport(array $customTabela, array $customColuna, array $tipoDivergente, array $tenants): string
{
    $md = "# Customizacoes por cliente\n\n";
    $md .= "Gerado por tools/check-models.php. Core = o que existe igual em TODOS os "
        . count($tenants) . " bancos de cliente (" . implode(', ', $tenants) . ").\n";
    $md .= "Tudo que aparece aqui e customizacao e NAO entra no model base "
        . "(pertence a pasta do cliente: `clientes/<id>/models/`).\n\n";

    $md .= "## Tabelas que so alguns clientes tem\n\n";
    foreach ($customTabela as $table => $donos) {
        $md .= "- `{$table}` -> " . implode(', ', $donos) . "\n";
    }

    $md .= "\n## Colunas extras em tabelas core\n\n";
    foreach ($customColuna as $tenant => $colunas) {
        $md .= "### {$tenant}\n\n";
        foreach ($colunas as $coluna) {
            $md .= "- `{$coluna}`\n";
        }
        $md .= "\n";
    }

    if ($tipoDivergente !== []) {
        $md .= "## Mesma coluna, tipo diferente entre clientes\n\n";
        $md .= "Ficam no core (existem em todos), mas o schema esta fora de sincronia:\n\n";
        foreach ($tipoDivergente as $coluna => $tipos) {
            $md .= "- `{$coluna}`: " . json_encode($tipos) . "\n";
        }
    }

    return $md;
}

function section(string $title, array $lines): void
{
    echo PHP_EOL . $title . PHP_EOL;
    echo str_repeat('-', 70) . PHP_EOL;

    if ($lines === []) {
        echo '  (nenhum)' . PHP_EOL;
        return;
    }

    foreach ($lines as $line) {
        echo ($line === '' ? '' : '  - ') . $line . PHP_EOL;
    }
}
