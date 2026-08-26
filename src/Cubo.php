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
    /**
     * @param string $appFolder Pasta da aplicacao (ex.: 'App'), onde vive
     *                          config/config.ini.
     * @param string|null $mainController Controlador chamado em TODAS as
     *                          requisicoes, em FQCN. Sem ele, o controlador sai
     *                          da propria URL.
     * @param string $controllerNamespace Prefixo dos controladores resolvidos
     *                          pela URL (ex.: 'App\\Controllers\\'), ja que a
     *                          URL fornece so o nome curto.
     * @param Router $router Injetavel para teste; o padrao serve em producao.
     */
    public function __construct(
        private readonly string $appFolder,
        private readonly ?string $mainController = null,
        private readonly string $controllerNamespace = '',
        private readonly Router $router = new Router(),
    ) {
    }

    /**
     * Prepara o ambiente e despacha. Excecoes sobem: quem monta a resposta de
     * erro e o index.php da app, com o Cubo\ErrorHandler.
     */
    public function run(): void
    {
        $this->bootstrap();
        $this->dispatch($this->router->parseUrl());
    }

    /**
     * Configuracao do framework, e nada mais.
     *
     * Fora daqui de proposito: o autoload (Composer, carregado pelo index.php da
     * app) e o wiring de classes da APP -- View padrao e repositorio de chaves --
     * que o framework nao pode nomear. Ver o guia no rodape.
     */
    public function bootstrap(): void
    {
        $config = Config::getInstance();
        $config->setAppFolder($this->appFolder);
        $config->initializeConfig();
    }

    /**
     * Instancia o controlador da rota, roda o ciclo e renderiza.
     *
     * Caminho unico para os dois casos: getModule() devolve $this quando nao ha
     * modulo resolvido.
     */
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

    /**
     * Descobre e instancia o controlador que atende a rota.
     *
     * Exige um Cubo\Controller: sem isso, quem monta a URL escolheria qual classe
     * o framework instancia.
     *
     * @throws ControllerNotFoundException
     */
    private function resolveController(Route $route): Controller
    {
        $class = $this->mainController
            ?? $this->controllerNamespace . ucfirst($route->controller) . 'Controller';

        if (!class_exists($class) || !is_subclass_of($class, Controller::class)) {
            throw ControllerNotFoundException::for($class);
        }

        return new $class($route);
    }
}

/*
 * GUIA DE MIGRACAO - Cubo -> Cubo\Cubo
 *
 * COMO FICA O index.php DA APP
 *
 *     require __DIR__ . '/../vendor/autoload.php';
 *
 *     use Cubo\Controller;
 *     use Cubo\Cubo;
 *     use App\Views\DefaultView;
 *
 *     Controller::setDefaultViewFactory(fn() => DefaultView::getInstance());
 *
 *     (new Cubo('App', 'App\Controllers\CoreController'))->run();
 *
 * RENOMEADOS
 *   getAppFoldeRoot -> Cubo\Config::getInstance()->getAppFolderRoot()
 *   parseUrl -> Cubo\Routing\Router::parseUrl(): Route
 *
 * NOVOS
 *   bootstrap() -- configuracao do framework, separada do despacho
 *   dispatch(Route): Controller
 *
 * MUDOU
 *   setAppFolder / setMainController -- viraram parametros do construtor
 *   controlador vindo da URL -- tem de ser um Cubo\Controller
 *   prefixo de namespace dos controladores -- parametro do construtor
 *
 * A FACHADA ESTATICA CAIU
 *   Cubo::Config() -> Cubo\Config::getInstance()
 *   Cubo::Session() -> Cubo\Session::getInstance()
 *   Cubo::Db() -> Cubo\Database\Db::getInstance()
 *   Cubo::Router() -> new Cubo\Routing\Router()
 *   Cubo::Auth() -> new Cubo\Auth\Auth($repositorioDeChaves)
 *   Cubo::Tools() -> as classes de Cubo\Tools, importadas uma a uma
 *   Cubo::Autoload() -> Composer
 *
 * DESCARTADOS
 *   sendMail X sem substituto no framework
 *   getPhpMailer / getPhpMailerS X instancie PHPMailer\PHPMailer\PHPMailer
 *   getInstance X o kernel e instanciado uma vez pelo index.php
 *   setConfig / getSecurit / _loadAppIni X stubs vazios
 *   _initializeSecurity / activeSecurity X sem substituto, NAO REINTRODUZIR
 *   _initializeAutoload / _registerAppFolder X Composer
 *   _app_folder_root X ver Cubo\Config
 */
