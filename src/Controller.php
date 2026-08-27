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
 * @author v2: Mateus - github.com/eeomts
 */
abstract class Controller
{
    protected View $_view;

    /** Rota que originou a requisicao. */
    protected ?Route $_route;

    /** @var Db|null */
    protected ?Db $_db = null;

    /** Controlador do modulo que atende a requisicao. */
    protected ?Controller $_module = null;

    /** @var (\Closure(): View)|null */
    private static ?\Closure $_defaultViewFactory = null;

    /** @param View|null $view se omitida, usa a fabrica padrao */
    public function __construct(?Route $route = null, ?View $view = null)
    {
        $this->_route = $route;
        $this->_view = $view ?? self::makeDefaultView();
    }

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
