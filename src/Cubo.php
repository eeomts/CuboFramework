<?php

namespace Cubo;

use Cubo\Exceptions\ActionNotFoundException;
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
    /** Devolvido quando o arquivo VERSION nao veio junto do framework. */
    public const VERSAO_DESCONHECIDA = 'desconhecida';

    private static ?string $versao = null;

    private MiddlewareStack $middlewares;

    public static function version(): string
    {
        if (self::$versao !== null) {
            return self::$versao;
        }

        $arquivo = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'VERSION';

        $lido = is_file($arquivo)
            ? trim((string) file_get_contents($arquivo))
            : '';

        return self::$versao = $lido !== '' ? $lido : self::VERSAO_DESCONHECIDA;
    }

    /**
     * @param string $appRoot raiz ABSOLUTA da app, onde vive config/config.ini
     * @param string|null $mainController FQCN chamado em TODAS as requisicoes; sem ele, sai da URL
     * @param string $controllerNamespace prefixo dos controladores resolvidos pela URL
     * @param Router $router injetavel para teste
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

        $resposta = $this->pilhaDaRota($route)->execute(
            $request,
            fn (Request $req): Response => $this->render($route, $req),
        );

        $resposta->send();
    }

    /** Globais primeiro, depois os da rota. */
    private function pilhaDaRota(Route $route): MiddlewareStack
    {
        if ($route->middleware === []) {
            return $this->middlewares;
        }

        $pilha = new MiddlewareStack();

        foreach ([...$this->middlewares->all(), ...$route->middleware] as $middleware) {
            $pilha->add($middleware);
        }

        return $pilha;
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

    /**
     * Duas formas de despachar convivem: rota DECLARADA diz a action e o kernel
     * chama; rota por CONVENCAO cai em index() e o controlador despacha.
     */
    public function dispatch(Route $route, Request $request): Controller
    {
        $controller = $this->resolveController($route, $request);

        $controller->initialize();

        if ($route->ehDeclarada()) {
            $this->invocarAction($controller, $route);
        } else {
            $controller->index();
        }

        $controller->getModule()->display();

        return $controller;
    }

    /** @throws ActionNotFoundException */
    private function invocarAction(Controller $controller, Route $route): void
    {
        if (!method_exists($controller, $route->method)) {
            throw ActionNotFoundException::for($controller::class, $route->method);
        }

        $reflexao = new \ReflectionMethod($controller, $route->method);

        # metodo herdado do Controller nao e alvo de rota: display() ou setView() viraria execucao arbitraria
        if (!$reflexao->isPublic() || $reflexao->getDeclaringClass()->getName() === Controller::class) {
            throw ActionNotFoundException::for($controller::class, $route->method);
        }

        $controller->{$route->method}();
    }

    /** @throws ControllerNotFoundException */
    private function resolveController(Route $route, Request $request): Controller
    {
        $class = $this->mainController
            ?? $route->controllerClass
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
