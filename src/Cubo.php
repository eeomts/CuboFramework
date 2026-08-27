<?php

namespace Cubo;

use Cubo\Exceptions\ControllerNotFoundException;
use Cubo\Routing\Route;
use Cubo\Routing\Router;

/**
 * Kernel do framework: prepara o ambiente e despacha a requisicao.
 *
 * Nao ha fachada estatica: cada arquivo declara o que usa.
 *
 *     use Cubo\Session;
 *     $nome = Session::getInstance()->get('usuario.nome');
 *
 * @package Cubo
 * @author v1: João (Cubo)
 * @author v2: Mateus - github.com/eeomts
 */
final class Cubo
{
    public const VERSION = '2.1.0-dev';

    /**
     * @param string $appRoot Caminho ABSOLUTO da raiz da aplicacao, onde vive
     *                          config/config.ini. O index.php passa __DIR__.
     * @param string|null $mainController Controlador chamado em TODAS as
     *                          requisicoes, em FQCN. Sem ele, o controlador sai
     *                          da propria URL.
     * @param string $controllerNamespace Prefixo dos controladores resolvidos
     *                          pela URL (ex.: 'App\\Controllers\\'), ja que a
     *                          URL fornece so o nome curto.
     * @param Router $router Injetavel para teste; o padrao serve em producao.
     */
    public function __construct(
        private readonly string $appRoot,
        private readonly ?string $mainController = null,
        private readonly string $controllerNamespace = '',
        private readonly Router $router = new Router(),
    ) {
    }

    /** Despacha a requisicao; excecoes sobem para o index.php da app. */
    public function run(): void
    {
        $this->bootstrap();
        $this->dispatch($this->router->parseUrl());
    }

    /** Configuracao do framework somente. */
    public function bootstrap(): void
    {
        $config = Config::getInstance();
        $config->setAppRoot($this->appRoot);
        $config->initializeConfig();

        (new Bootstrapper($config, $config->getAppRoot()))->boot();
    }

    /** Instancia, roda e renderiza o controlador. */
    public function dispatch(Route $route): Controller
    {
        $controller = $this->resolveController($route);

        $controller->initialize();
        $controller->index();

        // Renderiza a view do MODULO que o controlador principal resolveu; sem
        // modulo, a dele mesmo. Ver Controller::getModule().
        $controller->getModule()->display();

        return $controller;
    }

    /** @throws ControllerNotFoundException */
    private function resolveController(Route $route): Controller
    {
        $class = $this->mainController
            ?? $this->controllerNamespace() . ucfirst($route->controller) . 'Controller';

        if (!class_exists($class) || !is_subclass_of($class, Controller::class)) {
            throw ControllerNotFoundException::for($class);
        }

        return new $class($route);
    }

    /** Construtor leva precedencia sobre o [app] controllers do config.ini. */
    private function controllerNamespace(): string
    {
        if ($this->controllerNamespace !== '') {
            return $this->controllerNamespace;
        }

        $declared = Config::getInstance()->getConfig('ini.app.controllers');

        return is_string($declared) ? $declared : '';
    }
}
