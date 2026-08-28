<?php

namespace Cubo;

use Cubo\Exceptions\ControllerNotFoundException;
use Cubo\Http\Middleware;
use Cubo\Http\MiddlewareStack;
use Cubo\Http\Request;
use Cubo\Http\Response;
use Cubo\Routing\Route;
use Cubo\Routing\Router;

/**
 * Kernel do framework: prepara o ambiente e despacha a requisicao.
 * @package Cubo
 * @author v1: João (Cubo)
 * @author v2: Mateus - github.com/eeomts
 */
final class Cubo
{
    public const VERSION = '2.1.0-dev';

    private MiddlewareStack $middlewares;

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
        $this->middlewares = new MiddlewareStack();
    }

    /** Despacha a requisicao; excecoes sobem para o index.php da app. */
    public function run(): void
    {
        $this->bootstrap();

        $request = new Request(trustProxy: $this->confiaNoProxy());
        $route = $this->router->parseUrl($request);

        $resposta = $this->middlewares->execute(
            $request,
            fn (Request $req): Response => $this->render($route, $req),
        );

        $resposta->send();
    }

    /** [app] trusted_proxy no config.ini; sem ele o X-Forwarded-Proto e ignorado. */
    private function confiaNoProxy(): bool
    {
        return (bool) Config::getInstance()->getConfig('ini.app.trusted_proxy');
    }

    public function middleware(Middleware|string $middleware): self
    {
        $this->middlewares->add($middleware);

        return $this;
    }

    /**
     * Renderiza capturando a saida, porque a View escreve direto no output: sem
     * isso os cabecalhos sairiam depois do corpo e o middleware nao teria o que
     * inspecionar.
     *
     * Nao define Content-Type e herda o status ja emitido, para nao atropelar o
     * controlador que mandou cabecalho na mao (download de PDF, por exemplo).
     */
    private function render(Route $route, Request $request): Response
    {
        ob_start();

        try {
            $this->dispatch($route, $request);
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (new Response())
            ->status(http_response_code() ?: 200)
            ->body((string) ob_get_clean());
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
    public function dispatch(Route $route, Request $request): Controller
    {
        $controller = $this->resolveController($route, $request);

        $controller->initialize();
        $controller->index();

        $controller->getModule()->display();

        return $controller;
    }

    /** @throws ControllerNotFoundException */
    private function resolveController(Route $route, Request $request): Controller
    {
        $class = $this->mainController
            ?? $this->controllerNamespace() . ucfirst($route->controller) . 'Controller';

        if (!class_exists($class) || !is_subclass_of($class, Controller::class)) {
            throw ControllerNotFoundException::for($class);
        }

        return new $class($route, $request);
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
