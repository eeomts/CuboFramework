<?php

namespace Cubo;

use Cubo\Database\Db;
use Cubo\Logging\FileLogger;
use Cubo\Routing\RouteCollection;
use Cubo\Routing\Router;
use Cubo\View\Imports;
use Cubo\View\View;

/**
 * Aplica na app o que a secao [app] do config.ini declara.
 *
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
final class Bootstrapper
{
    public function __construct(
        private readonly Config $config,
        private readonly string $appRoot,
    ) {}

    public function boot(): void
    {
        $this->applyRuntime();
        $this->applyTemplateRoots();
        $this->applyRoutes();
        $this->applyImports();
        $this->applyDefaultView();
        $this->applyErrorHandling();
        $this->applyDatabase();
    }

    /** Boot enxuto para a linha de comando. */
    public function bootConsole(): void
    {
        $this->applyRuntime();
        $this->applyDatabase();
    }

    /** Carrega a tabela de rotas declarada em [app] routes. */
    private function applyRoutes(): void
    {
        $arquivo = $this->string('routes');

        if ($arquivo === '') {
            return;
        }

        $caminho = $this->absolutePath($arquivo, false);

        if (!is_file($caminho)) {
            throw new \Cubo\Exceptions\CuboException(
                "[app] routes aponta para um arquivo que nao existe: {$caminho}"
            );
        }

        $rotas = require $caminho;

        if (!$rotas instanceof RouteCollection) {
            throw new \Cubo\Exceptions\CuboException(
                "[app] routes: {$caminho} precisa devolver uma " . RouteCollection::class . '.'
            );
        }

        $this->config->setConfig(Router::ROUTES, $rotas);
    }

    /**
     * Monta o carregador de assets declarado em [app] imports. O caminho em
     * disco serve para o filemtime da versao.
     */
    private function applyImports(): void
    {
        $arquivo = $this->string('imports');

        if ($arquivo === '') {
            return;
        }

        $host = (string) $this->config->getConfig('ini.cubo.host');
        $base = (string) (parse_url($host, PHP_URL_PATH) ?: '/');

        $this->config->setConfig(View::IMPORTS, new Imports(
            arquivo: $this->absolutePath($arquivo, false),
            publicRoot: $this->absolutePath('public', false),
            basePath: $base,
        ));
    }

    private function applyRuntime(): void
    {
        $charset = $this->string('charset');
        if ($charset !== '') {
            ini_set('default_charset', $charset);
            mb_internal_encoding($charset);
        }

        $timezone = $this->string('timezone');
        if ($timezone !== '') {
            date_default_timezone_set($timezone);
        }
    }

    private function applyTemplateRoots(): void
    {
        $declared = $this->config->getConfig('ini.app.templates');

        if ($declared === null || $declared === '') {
            return;
        }

        $roots = [];
        foreach ((array) $declared as $root) {
            $roots[] = $this->absolutePath((string) $root);
        }

        $this->config->setConfig('template_roots', $roots);
    }

    private function applyDefaultView(): void
    {
        $class = $this->string('view');

        if ($class === '') {
            return;
        }

        if (!is_subclass_of($class, View::class)) {
            throw new \Cubo\Exceptions\CuboException(
                "[app] view aponta para '{$class}', que nao existe ou nao estende " . View::class
            );
        }

        Controller::setDefaultViewFactory(fn(): View => new $class());
    }

    private function applyErrorHandling(): void
    {
        if ($this->config->getConfig('ini.cubo.envi') === 'development') {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);

            return;
        }

        ini_set('display_errors', '0');

        $log = $this->string('log');
        if ($log === '') {
            return;
        }

        (new ErrorHandler(
            new FileLogger($this->absolutePath($log, false)),
            (string) $this->config->getConfig('ini.cubo.host')
        ))->register();
    }

    private function applyDatabase(): void
    {
        if (!is_array($this->config->getConfig('ini.database'))) {
            return;
        }

        Db::getInstance()->connectFromConfig();
    }

    private function string(string $key): string
    {
        $value = $this->config->getConfig('ini.app.' . $key);

        return is_string($value) ? trim($value) : '';
    }

    /** Caminho do ini e relativo ao appRoot, salvo quando ja vem absoluto. */
    private function absolutePath(string $path, bool $directory = true): string
    {
        $isAbsolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:/', $path) === 1;

        $full = $isAbsolute
            ? $path
            : $this->appRoot . DIRECTORY_SEPARATOR . ltrim($path, '/\\');

        $full = rtrim($full, '/\\');

        return $directory ? $full . DIRECTORY_SEPARATOR : $full;
    }
}
