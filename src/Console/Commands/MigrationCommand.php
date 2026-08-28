<?php

namespace Cubo\Console\Commands;

use Cubo\Bootstrapper;
use Cubo\Config;
use Cubo\Console\Command;
use Cubo\Database\Db;
use Cubo\Database\Migrations\Migrator;
use Cubo\Exceptions\CuboException;

/**
 * Base dos comandos de migration.
 *
 * Todos precisam da mesma coisa: bootar a app da pasta atual para alcancar a
 * conexao declarada no config.ini. Sem isso o comando nao teria banco nenhum.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
abstract class MigrationCommand implements Command
{
    /** Onde as migrations moram, quando o ini nao diz outra coisa. */
    private const PASTA_PADRAO = 'database/migrations';

    /** @throws CuboException se o config.ini nao declarar banco */
    protected function migrator(): Migrator
    {
        $raiz = $this->appRoot();

        $config = Config::getInstance();
        $config->setAppRoot($raiz);
        $config->initializeConfig();

        // boot de console, nao o de HTTP: migration nao renderiza nada
        (new Bootstrapper($config, $raiz))->bootConsole();

        if (!is_array($config->getConfig('ini.database'))) {
            throw new CuboException(
                'Nenhum banco declarado: descomente a secao [database.<location>] '
                . 'em config/config.ini antes de rodar migration.'
            );
        }

        return new Migrator(
            Db::getInstance()->getConnection(),
            $raiz . DIRECTORY_SEPARATOR . $this->pastaDeMigrations()
        );
    }

    /** A app e a pasta de onde o comando foi chamado. */
    protected function appRoot(): string
    {
        return rtrim((string) getcwd(), '/\\');
    }

    private function pastaDeMigrations(): string
    {
        $declarada = Config::getInstance()->getConfig('ini.app.migrations');

        $pasta = is_string($declarada) && trim($declarada) !== ''
            ? trim($declarada)
            : self::PASTA_PADRAO;

        return str_replace('/', DIRECTORY_SEPARATOR, trim($pasta, '/\\'));
    }
}
