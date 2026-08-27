<?php

namespace Cubo;

/**
 * Configuracoes gerais do sistema
 * 
 * @package Cubo
 * @author v1 João
 * 
 * V2 - core cubo atualizado para php 8+
 * @package Cubo
 * @author Mateus - github.com/eeomts
 * 
 */

class Config
{

    private static ?Config $_instance = null;

    /**
     * Variavel onde sera armazenada as configuracoes
     * 
     * @var array $_config
     */
    private array $_config = [];

    /**
     * Caminho ABSOLUTO da raiz do aplicativo (onde vive config/config.ini).
     * Definido no boot via setAppRoot(); usado por _loadIniFile().
     *
     * @var string|null $_appRoot
     */
    private ?string $_appRoot = null;

    private function __construct() {}

    public static function getInstance(): static
    {

        if (static::$_instance === null) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    public function initializeConfig()
    {
        // Carrega o config.ini antes de definir constantes que dependem dele
        // (ex.: CUBO_DIR_NAME usa getConfig('ini.cubo.host')).
        $this->_loadIniFile();

        if (isset($_SERVER['HTTPS']))
            $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
        else
            $protocol = 'http';

        if (!defined("SERVER"))
            define('SERVER', $_SERVER['HTTP_HOST']);

        if (!defined("WEB"))
            define('WEB', $_SERVER['REQUEST_URI']);

        if (!defined("CUBO_DIR_NAME"))
            define('CUBO_DIR_NAME', str_replace($protocol . '://', '', $this->getConfig('ini.cubo.host')));

        //Pasta raiz do framework
        // DIRECTORY_SEPARATOR, nao DS: a constante DS e definida pelo index.php
        // da APP, entao o framework dependia de um global que nao e dele. Era o
        // debito registrado no REFAC 4, resolvido aqui tirando a dependencia (a
        // app segue livre para definir DS para o codigo dela).
        if (!defined("CUBO_ROOT"))
            define('CUBO_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);

        if (!defined("CUBO_RAIZ"))
            define('CUBO_RAIZ', $this->getAppRoot() . DIRECTORY_SEPARATOR);
    }


    public function setConfig(string $index, mixed $value): void
    {
        $this->_config[$index] = $value;
    }

    public function getConfig(string $index): mixed
    {
        $keys = explode('.', $index);
        $value = $this->_config;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Informa a raiz do aplicativo. Chamar no boot, antes de initializeConfig().
     *
     * @param string $path caminho absoluto (ex.: __DIR__)
     */
    public function setAppRoot(string $path): void
    {
        $this->_appRoot = rtrim($path, '/\\');
    }

    /**
     * Retorna a raiz absoluta do aplicativo.
     *
     * @throws \RuntimeException se a raiz nao foi definida via setAppRoot()
     */
    public function getAppRoot(): string
    {
        if ($this->_appRoot === null) {
            throw new \RuntimeException('Raiz da app nao definida; chame setAppRoot() antes.');
        }
        return $this->_appRoot;
    }

    private function _loadIniFile(): void
    {
        $iniPath = $this->getAppRoot()
            . DIRECTORY_SEPARATOR . 'config'
            . DIRECTORY_SEPARATOR . 'config.ini';

        if (!is_file($iniPath)) {
            throw new \Cubo\Exceptions\CuboException("config.ini nao encontrado em: {$iniPath}");
        }

        #lê o arquivo
        $ini = parse_ini_file($iniPath, true);


        $location = $ini['cubo']['location'];
        // $host = $ini['cubo']['host.' . $location];

        $cubo = [
            'host'  => $ini['cubo']['host.' . $location],
            'envi'  => $ini['cubo']['enviroment'],
            'table_prefix' => $ini['cubo']['table_prefix'],
            'database_prefix' => $ini['cubo']['database_prefix'],
            'path_prefix' => $ini['cubo']['path_prefix'],
            'servidor' => $ini['cubo']['servidor'],
            'redir' => $ini['cubo']['redir'],
            'versao' => $ini['cubo']['versao'],
            'url_login' => $ini['cubo']['url_login']
        ];

        $this->setConfig('ini', [
            'cubo'     => $cubo,
            'database' => $ini['database.' . $location],
        ]);
    }
}
