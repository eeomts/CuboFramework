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
     * Nome da pasta do aplicativo principal (onde vive config/config.ini).
     * Definido no boot via setAppFolder(); usado por _loadIniFile().
     *
     * @var string|null $_appFolder
     */
    private ?string $_appFolder = null;

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
            define('CUBO_RAIZ', dirname(dirname(dirname(CUBO_ROOT))) . DIRECTORY_SEPARATOR);
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
     * Informa ao framework a pasta do aplicativo principal.
     * Deve ser chamado no boot, antes de initializeConfig().
     *
     * @param string $name nome da pasta (ex.: 'Cms', 'Ead')
     */
    public function setAppFolder(string $name): void
    {
        $this->_appFolder = $name;
    }

    /**
     * Retorna a pasta onde o aplicativo principal esta sendo executado.
     *
     * Substitui Cubo::getAppFoldeRoot() -- a posse migrou do kernel para o
     * Config, que passa a ser auto-suficiente para achar seu config.ini.
     *
     * @throws \RuntimeException se a pasta nao foi definida via setAppFolder()
     */
    public function getAppFolderRoot(): string
    {
        if ($this->_appFolder === null) {
            throw new \RuntimeException('Pasta da app nao definida; chame setAppFolder() antes.');
        }
        return $this->_appFolder;
    }

    private function _loadIniFile(): void
    {
        $iniPath = dirname(__FILE__, 3)
            . DIRECTORY_SEPARATOR . $this->getAppFolderRoot()
            . DIRECTORY_SEPARATOR . 'config'
            . DIRECTORY_SEPARATOR . 'config.ini';

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
