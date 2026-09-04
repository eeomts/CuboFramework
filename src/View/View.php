<?php

namespace Cubo\View;

use Cubo\Config;
use Cubo\Exceptions\TemplateNotFoundException;
use Cubo\Helper;
use Cubo\Security;

/**
 * Molde do processo de renderizacao. Usa o padrao Composite: uma View tem
 * filhas (outras Views / helpers), e renderizar a raiz renderiza a arvore.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_View)
 * @author v2: Mateus - github.com/eeomts
 * @see http://en.wikipedia.org/wiki/Composite_pattern
 */
abstract class View implements Helper
{
    /** @var list<View> filhos do Composite */
    protected array $_children = [];

    /** @var array<string, mixed> */
    protected array $_params = [];

    private string $_template = '';

    public const TEMPLATE_ROOTS = 'template_roots';

    public const IMPORTS = 'imports';

    /**
     * @example addChild(new MenuHelper())
     * @example addChild('ImportHelper')
     */
    public function addChild(View|string $child): void
    {
        if (is_string($child)) {
            if (!is_subclass_of($child, self::class)) {
                throw new \InvalidArgumentException(
                    "addChild espera uma View; '{$child}' nao existe ou nao estende " . self::class . '.'
                );
            }
            $child = new $child();
        }

        $this->_children[] = $child;
    }

    /**
     * @return list<View>
     */
    public function getChildren(): array
    {
        return $this->_children;
    }

    /** Registra um parametro para o template consumir. */
    public function addParam(string $label, mixed $value): void
    {
        $this->_params[$label] = $value;
    }

    /** Le um parametro CRU (sem escape). */
    public function getParam(string $param, mixed $default = null): mixed
    {
        return $this->_params[$param] ?? $default;
    }

    /**
     * Le um parametro escapado para HTML: XSS se resolve com escape na SAIDA.
     *
     * O getParam() segue cru de proposito: muitos params carregam HTML montado
     * (grids, botoes) e escapar tudo por padrao quebraria a tela.
     *
     * Params nao imprimiveis (array, object) devolvem o default escapado.
     */
    public function escape(string $param, string $default = ''): string
    {
        $value = $this->_params[$param] ?? $default;

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return Security::escape($default);
        }

        return Security::escape((string) $value);
    }

    /**
     * Tags de CSS e JS dos grupos pedidos. Sem `[app] imports` declarado
     * devolve vazio.
     */
    public function assets(string ...$grupos): string
    {
        $imports = Config::getInstance()->getConfig(self::IMPORTS);

        return $imports instanceof Imports ? $imports->render(...$grupos) : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->_params;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateParams(array $params): void
    {
        $this->_params = $params;
    }

    /** Informa qual arquivo html a view deve carregar. */
    public function setTemplate(string $template): void
    {
        $this->_template = $template;
    }

    public function getTemplate(): string
    {
        return $this->_template;
    }

    /**
     * Renderiza a arvore: primeiro os filhos, depois o proprio template.
     *
     * Os params viram variaveis locais (extract) e a view fica acessivel como
     * $view, contrato que os templates usam.
     */
    public function render(): void
    {
        global $view;

        $this->_setDefaultParams();

        # a variavel de loop nao pode ser $view: sobrescreveria a global durante o render dos filhos
        foreach ($this->_children as $child) {
            $this->_callRender($child);
        }

        # resolvido antes do extract, que pode sobrescrever variaveis locais
        $cuboTemplate = $this->resolveTemplate();

        extract($this->_params);

        # atribuida DEPOIS do extract: antes, um param chamado 'view' derrubaria a $view dos templates
        $view = $this;

        if ($cuboTemplate !== null) {
            include $cuboTemplate;
        }
    }

    /**
     * Repassa os params acumulados e recolhe o que o filho produziu: helpers
     * adicionam params que o template do pai le depois.
     */
    protected function _callRender(View $child): void
    {
        foreach ($child->getChildren() as $grandchild) {
            $this->_callRender($grandchild);
        }

        $child->updateParams($this->_params);
        $child->render();
        $this->_params = $child->getParams();
    }

    /**
     * @return string|null null quando nenhum template foi setado, o caso dos helpers
     * @throws TemplateNotFoundException template setado que nao existe em nenhuma raiz
     */
    private function resolveTemplate(): ?string
    {
        if ($this->_template === '') {
            return null;
        }

        $roots = Config::getInstance()->getConfig(self::TEMPLATE_ROOTS);
        $procuradas = [];

        foreach ((array) $roots as $root) {
            if (!is_string($root) || $root === '') {
                continue;
            }

            $procuradas[] = $root;

            if (is_file($root . $this->_template)) {
                return $root . $this->_template;
            }
        }

        throw TemplateNotFoundException::for($this->_template, $procuradas);
    }

    /**
     * Hook para params que todo template usa, evitando que cada controlador
     * reenvie os mesmos dados. Protected para a app sobrescrever.
     */
    protected function _setDefaultParams(): void
    {
    }
}
