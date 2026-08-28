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

    /** Chave de config com as raizes de template, na ordem de precedencia. */
    public const TEMPLATE_ROOTS = 'template_roots';

    /**
     * Adiciona uma View filha. Aceita a instancia ou o nome da classe.
     *
     * @example addChild(new MenuHelper())
     * @example addChild('ImportHelper')
     *
     * O v1 fazia new $child() as cegas: um nome errado virava fatal error
     * cru. Aqui a classe e validada antes de instanciar.
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

    /**
     * Registra um parametro para o template consumir.
     */
    public function addParam(string $label, mixed $value): void
    {
        $this->_params[$label] = $value;
    }

    /**
     * Le um parametro CRU (sem escape).
     *
     * v1 fazia return $this->_params[$param] direto: no PHP 8 isso emite
     * "Warning: Undefined array key" quando o param nao foi setado. Agora
     * um param ausente devolve o default.
     *
     * Para imprimir dado vindo do usuario em HTML, prefira escape().
     */
    public function getParam(string $param, mixed $default = null): mixed
    {
        return $this->_params[$param] ?? $default;
    }

    /**
     * Le um parametro ja escapado para HTML (defesa contra XSS).
     *
     * O WAF do v1 (Cubo_Security::filterValue) foi removido no REFAC 2 sob a
     * premissa de que XSS se resolve com escape na SAIDA -- este metodo e essa
     * saida. getParam() segue cru de proposito: muitos params carregam HTML
     * montado (grids, botoes) e escapar tudo por padrao quebraria a tela.
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

    /**
     * Informa qual arquivo html a view deve carregar.
     */
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
     * Os params viram variaveis locais (extract) e a propria view fica
     * acessivel como $view global -- contrato que os templates usam hoje
     * ($view->getParam('x')).
     */
    public function render(): void
    {
        global $view;

        $this->_setDefaultParams();

        // v1 usava $view como variavel do foreach, ou seja, sobrescrevia a
        // global durante o render dos filhos. A variavel de loop agora e outra.
        foreach ($this->_children as $child) {
            $this->_callRender($child);
        }

        // resolvido antes do extract, que pode sobrescrever variaveis locais
        $cuboTemplate = $this->resolveTemplate();

        extract($this->_params);

        // atribuida DEPOIS do extract: no v1 vinha antes, entao um param
        // chamado 'view' derrubava a $view que os templates dependem
        $view = $this;

        if ($cuboTemplate !== null) {
            include $cuboTemplate;
        }
    }

    /**
     * Renderiza recursivamente um filho, repassando os params acumulados e
     * recolhendo o que ele produziu (helpers adicionam params que o template
     * do pai le depois).
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
     * Descobre o caminho do template nas raizes configuradas.
     *
     * @return string|null null quando nenhum template foi setado (o caso dos
     *                     helpers, que so processam params e nao tem html)
     * @throws TemplateNotFoundException quando ha template setado mas ele nao
     *                                   existe em nenhuma raiz (no v1 isso era
     *                                   uma pagina em branco silenciosa)
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
     * reenvie os mesmos dados.
     *
     * Era private no v1 -- ou seja, nenhuma subclasse conseguia sobrescrever,
     * o que anulava o proposito descrito no proprio docblock dele. Agora e
     * protected e pode de fato ser implementado (ex.: DefaultView).
     */
    protected function _setDefaultParams(): void
    {
    }
}
