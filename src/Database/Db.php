<?php

/**
 * Acesso ao banco de dados; gerencia conexoes e SQL cru.
 *
 * @package Cubo
 * @author v1: Cristiano M. Gomes
 * @author v2: Mateus - github.com/eeomts
 */

namespace Cubo\Database;

use Cubo\Config;
use Cubo\Tools\Str;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PDO;
use PDOStatement;
use RuntimeException;

class Db
{
    public const DEFAULT_CONNECTION = 'default';

    private static ?Db $_instance = null;

    /**
     * O Capsule e o "Doctrine_Manager" do Eloquent: guarda as conexoes nomeadas
     * e resolve qual delas os Models usam.
     */
    private Capsule $_capsule;

    /** Nome da conexao ativa (ex-Doctrine_Manager::getCurrentConnection). */
    private string $_current = self::DEFAULT_CONNECTION;

    private function __construct()
    {
        $this->_capsule = new Capsule();

        // setAsGlobal + bootEloquent: sem isso o Model estatico (Cliente::find())
        // nao sabe em qual conexao rodar. Equivale ao ATTR_MODEL_LOADING do v1.
        $this->_capsule->setAsGlobal();
        $this->_capsule->bootEloquent();
    }

    public static function getInstance(): static
    {
        if (static::$_instance === null) {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

    /**
     * Registra a conexao lendo as credenciais do config.ini (secao [database.<location>]).
     *
     * user e pass continuam ofuscados no ini, por isso o cuboDecode.
     */
    public function connectFromConfig(string $name = self::DEFAULT_CONNECTION): void
    {
        $config = Config::getInstance();
        $database = $config->getConfig('ini.database');

        if (!is_array($database)) {
            throw new RuntimeException('Secao [database] ausente no config.ini');
        }

        $this->addConnection($name, [
            'driver' => $database['dbtype'] ?? 'mysql',
            'host' => $database['host'],
            'port' => $database['port'] ?? 3306,
            'database' => $database['db'],
            'username' => Str::cuboDecode($database['user']),
            'password' => Str::cuboDecode($database['pass']),
            // utf8 e apelido de utf8mb3: conexao mb3 com tabela mb4 perde caractere
            // e da illegal mix of collations. O ini manda; sem ele, utf8mb4.
            'charset' => $database['charset'] ?? 'utf8mb4',
            'collation' => $database['collation'] ?? 'utf8mb4_unicode_ci',
            // ex-setTablePrefix: o Eloquent tem prefixo nativo por conexao,
            // nao precisa mais do ATTR_TBLNAME_FORMAT do Doctrine.
            'prefix' => $config->getConfig('ini.cubo.table_prefix') ?: '',
        ]);
    }

    /**
     * Registra uma conexao nomeada com config explicita e passa a usa-la.
     * As conexoes antigas continuam vivas -- volte com changeConnection().
     */
    public function addConnection(string $name, array $config): void
    {
        $this->_capsule->addConnection($config, $name);
        $this->changeConnection($name);
    }

    /**
     * Torna ativa uma conexao ja registrada.
     */
    public function changeConnection(string $name): void
    {
        // getConnection lanca se o nome nao existir.
        $this->_capsule->getConnection($name);

        $this->_capsule->getDatabaseManager()->setDefaultConnection($name);
        $this->_current = $name;
    }

    public function getConnection(?string $name = null): Connection
    {
        return $this->_capsule->getConnection($name ?? $this->_current);
    }

    public function getCurrentConnectionName(): string
    {
        return $this->_current;
    }

    public function getPdo(): PDO
    {
        return $this->getConnection()->getPdo();
    }

    /**
     * Executa SQL cru e devolve o PDOStatement (da pra encadear ->fetchAll()).
     *
     * O bind daqui e a unica defesa contra SQLi em SQL cru, entao todo caller
     * deve passar $bindings:
     *
     *   executeSql("... WHERE id = ?", [$id])
     *
     * @param list<mixed>|array<string,mixed> $bindings
     */
    public function executeSql(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }

    public function getLastInsertId(): string
    {
        return $this->getPdo()->lastInsertId();
    }

    /**
     * Limpa a tabela e reseta o indice (truncate do proprio driver).
     */
    public function truncate(string $table): void
    {
        $this->getConnection()->table($table)->truncate();
    }

    public function close(): void
    {
        $this->getConnection()->disconnect();
    }
}
