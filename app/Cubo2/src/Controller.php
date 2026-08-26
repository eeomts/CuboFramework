<?php

namespace Cubo;

use Cubo\Database\Db;
use Cubo\Routing\Route;
use Cubo\View\View;

/**
 * Molde de todos os controladores do sistema.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Controller)
 *
 * V2 - core cubo atualizado para php 8+; a View passa a ser INJETADA (o v1
 * recebia $view no construtor e ignorava, cravando a DefaultView da app dentro
 * do framework).
 * @author v2: Mateus - github.com/eeomts
 */
abstract class Controller
{
    protected View $_view;

    /**
     * Rota que originou a requisicao.
     *
     * O v1 (e o porte do REFAC 7) guardava aqui o array associativo cru do
     * parseUrl. O REFAC 5 ja tinha trocado esse array pelo VO Route; o kernel e
     * o ponto onde as duas pontas se encontram, entao a troca acontece aqui.
     */
    protected ?Route $_route;

    /**
     * Conexao usada pelos controladores (herdada por todos eles).
     *
     * Continua sendo atribuida pelo proprio controlador -- exatamente como no
     * v1, onde a base so declarava a propriedade e cada controller fazia
     * $this->_db = Cubo::Db(). Alguns apontam para outra conexao, entao a base
     * nao pode decidir por eles.
     */
    protected ?Db $_db = null;

    /**
     * Controlador do modulo que de fato atende a requisicao.
     *
     * Quem preenche isto e o controlador principal da app (CoreController):
     * ele resolve qual modulo responde pela url e guarda a instancia aqui. O
     * orquestrador entao renderiza $core->getModule()->display() -- ou seja, a
     * view do MODULO, nao a do core. E o mecanismo de despacho do framework.
     */
    protected ?Controller $_module = null;

    /**
     * Fabrica da View usada quando nenhuma e injetada.
     *
     * E o bootstrap da APP que registra qual e a View padrao:
     *   Controller::setDefaultViewFactory(fn() => DefaultView::getInstance());
     *
     * Assim o framework nunca precisa conhecer a DefaultView pelo nome -- que
     * era o acoplamento invertido do v1 (o framework dependendo da app).
     *
     * @var (\Closure(): View)|null
     */
    private static ?\Closure $_defaultViewFactory = null;

    /**
     * @param Route|null $route Rota resolvida pelo Router
     * @param View|null $view View a usar; se omitida, cai na fabrica padrao
     */
    public function __construct(?Route $route = null, ?View $view = null)
    {
        $this->_route = $route;
        $this->_view = $view ?? self::makeDefaultView();
    }

    /**
     * @param \Closure(): View $factory
     */
    public static function setDefaultViewFactory(\Closure $factory): void
    {
        self::$_defaultViewFactory = $factory;
    }

    private static function makeDefaultView(): View
    {
        if (self::$_defaultViewFactory === null) {
            throw new \RuntimeException(
                'Nenhuma View foi injetada e nenhuma View padrao foi registrada; '
                . 'chame Controller::setDefaultViewFactory() no bootstrap da aplicacao.'
            );
        }

        return (self::$_defaultViewFactory)();
    }

    /**
     * Inicializacoes anteriores a execucao dos metodos principais.
     */
    public function initialize(): void
    {
    }

    /**
     * Executado quando a url nao traz instrucao de metodo.
     */
    public function index(): void
    {
    }

    public function display(): void
    {
        $this->_view->render();
    }

    /**
     * Devolve o modulo que atende a requisicao; sem modulo resolvido, o proprio
     * controlador responde.
     */
    public function getModule(): Controller
    {
        return $this->_module ?? $this;
    }

    public function setModule(Controller $module): void
    {
        $this->_module = $module;
    }

    public function getView(): View
    {
        return $this->_view;
    }

    public function setView(View $view): void
    {
        $this->_view = $view;
    }

    public function setRoute(?Route $route): void
    {
        $this->_route = $route;
    }

    public function getRoute(): ?Route
    {
        return $this->_route;
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Controller -> Cubo\Controller
 *
 * MANTIDOS
 *   initialize / index / display / getView / getModule
 *
 * RENOMEADOS
 *   setUrlParams -> setRoute
 *   getUrlParams -> getRoute
 *
 * NOVOS
 *   setModule(Controller) -- a app registra o modulo que atende a requisicao
 *   setDefaultViewFactory(Closure) -- o bootstrap da app registra a View padrao
 *
 * MUDOU
 *   __construct(?Route $route, ?View $view = null) -- recebia array de url params,
 *     e a View passada era ignorada
 *   setView(View $view) -- recebia o nome da classe
 *   $this->_db -- tipado ?Cubo\Database\Db
 *
 * DESCARTADOS
 *   getResources X sem substituto
 *
 * NA APP
 *   $this->_url_params['controller'] -> $route->controller
 *   $this->_url_params['method'] -> $route->method
 *   $this->_url_params['request'] -> $route->params
 */
