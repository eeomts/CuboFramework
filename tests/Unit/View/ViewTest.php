<?php

namespace Cubo\Tests\Unit\View;

use Cubo\Config;
use Cubo\Tests\Support\Views\FakeView;
use Cubo\Tests\Support\Views\SpyHelperView;
use Cubo\Exceptions\TemplateNotFoundException;
use Cubo\View\View;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

#[CoversClass(View::class)]
#[CoversClass(TemplateNotFoundException::class)]
final class ViewTest extends TestCase
{
    private FakeView $view;

    protected function setUp(): void
    {
        $this->view = new FakeView();
        SpyHelperView::reset();

        // O resolveTemplate() consulta o Config (singleton). Troca-se a
        // instancia por uma limpa, apontando a raiz para os templates fixture.
        $config = (new ReflectionClass(Config::class))->newInstanceWithoutConstructor();
        $config->setConfig(View::TEMPLATE_ROOTS, [__DIR__ . '/../../Support/templates/']);

        $this->setConfigInstance($config);
    }

    protected function tearDown(): void
    {
        $this->setConfigInstance(null);
    }

    private function setConfigInstance(?Config $config): void
    {
        $instance = new ReflectionProperty(Config::class, '_instance');
        $instance->setValue(null, $config);
    }

    // --- params ---

    public function testAddParamEGetParam(): void
    {
        $this->view->addParam('nome', 'Mateus');

        $this->assertSame('Mateus', $this->view->getParam('nome'));
    }

    public function testGetParamAusenteDevolveDefaultSemWarning(): void
    {
        // Bug do v1: return $this->_params[$param] direto -> no PHP 8 isso e
        // "Warning: Undefined array key" em cada um dos 279 pontos de template.
        $this->assertNull($this->view->getParam('nao_existe'));
        $this->assertSame('padrao', $this->view->getParam('nao_existe', 'padrao'));
    }

    // --- escape (a divida de XSS deixada pelo REFAC 2) ---

    public function testEscapeNeutralizaXssEGetParamSegueCru(): void
    {
        $payload = '<script>alert("x")</script>';
        $this->view->addParam('perigoso', $payload);

        $this->assertSame(
            '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
            $this->view->escape('perigoso')
        );

        // getParam continua cru de proposito: params com HTML montado (grids)
        // dependem disso.
        $this->assertSame($payload, $this->view->getParam('perigoso'));
    }

    public function testEscapeDeParamNaoImprimivelDevolveODefault(): void
    {
        $this->view->addParam('lista', ['a', 'b']);
        $this->view->addParam('objeto', new stdClass());

        $this->assertSame('', $this->view->escape('lista'));
        $this->assertSame('vazio', $this->view->escape('objeto', 'vazio'));
        $this->assertSame('', $this->view->escape('nem_existe'));
    }

    // --- composite ---

    public function testAddChildAceitaInstanciaENomeDeClasse(): void
    {
        $this->view->addChild(new SpyHelperView());
        $this->view->addChild(SpyHelperView::class);

        $this->assertCount(2, $this->view->getChildren());
        $this->assertContainsOnlyInstancesOf(SpyHelperView::class, $this->view->getChildren());
    }

    public function testAddChildRejeitaClasseInexistenteOuQueNaoEView(): void
    {
        // v1 fazia new $child() as cegas -> fatal error cru
        $this->expectException(InvalidArgumentException::class);

        $this->view->addChild('ClasseQueNaoExiste');
    }

    public function testAddChildRejeitaClasseQueNaoEstendeView(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->view->addChild(stdClass::class);
    }

    public function testRenderDoFilhoAconteceAntesEOsParamsSobemParaOPai(): void
    {
        $this->view->setTemplate('fake.php');
        $this->view->addParam('nome', 'Mateus');
        $this->view->addParam('perigoso', 'x');
        $this->view->addChild(new SpyHelperView());

        ob_start();
        $this->view->render();
        $saida = ob_get_clean();

        // o filho rendeu...
        $this->assertSame(1, SpyHelperView::$renders);
        // ...e o param que ele criou chegou ao template do pai, o que so e
        // possivel se o render dele aconteceu ANTES do include do pai
        $this->assertStringContainsString('|filho=ok', $saida);
    }

    // --- render / template ---

    public function testRenderExpoeViewGlobalEExtraiOsParams(): void
    {
        $this->view->setTemplate('fake.php');
        $this->view->addParam('nome', 'Mateus');
        $this->view->addParam('perigoso', '<b>');

        ob_start();
        $this->view->render();
        $saida = ob_get_clean();

        $this->assertStringContainsString('nome=Mateus', $saida);      // $view->getParam
        $this->assertStringContainsString('|extract=Mateus', $saida);  // extract() -> $nome
        $this->assertStringContainsString('|escapado=&lt;b&gt;', $saida);
        $this->assertStringContainsString('|cru=<b>', $saida);
    }

    public function testParamChamadoViewNaoDerrubaAViewGlobal(): void
    {
        // No v1 o "$view = $this" vinha ANTES do extract, entao um param
        // chamado 'view' sobrescrevia a $view que os templates usam.
        $this->view->setTemplate('fake.php');
        $this->view->addParam('nome', 'Mateus');
        $this->view->addParam('perigoso', 'x');
        $this->view->addParam('view', 'string qualquer');

        ob_start();
        $this->view->render();
        $saida = ob_get_clean();

        // se a global tivesse sido derrubada, o template daria erro fatal ao
        // chamar ->getParam() numa string
        $this->assertStringContainsString('nome=Mateus', $saida);
    }

    public function testTemplateInexistenteLancaExcecao(): void
    {
        // No v1 isso era uma pagina em branco silenciosa.
        $this->view->setTemplate('nao-existe.php');

        $this->expectException(TemplateNotFoundException::class);

        $this->view->render();
    }

    public function testViewSemTemplateRenderizaSemErro(): void
    {
        // E o caso dos helpers, que so processam params e nao tem html.
        ob_start();
        $this->view->render();
        $saida = ob_get_clean();

        $this->assertSame('', $saida);
    }

    public function testSetTemplateEGetTemplate(): void
    {
        $this->view->setTemplate('fake.php');

        $this->assertSame('fake.php', $this->view->getTemplate());
    }
}
