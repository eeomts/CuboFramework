<?php

namespace Cubo\View;

use Cubo\Exceptions\CuboException;

/**
 * Carrega as tags de CSS e JS declaradas num arquivo HTML.
 * @package Cubo
 * @author v1: Cristiano (ImportHelper)
 * @author v2: Mateus - github.com/eeomts
 */
final class Imports
{
    public const GRUPO_PADRAO = 'base';

    /** @var array<string, string>|null grupo => html cru */
    private ?array $grupos = null;

    /**
     * @param string $arquivo caminho absoluto do imports.html
     * @param string $publicRoot raiz servida; acha o arquivo e carimba a versao pelo filemtime
     * @param string $basePath subpasta onde a app esta montada ('/blog/')
     */
    public function __construct(
        private readonly string $arquivo,
        private readonly string $publicRoot,
        private readonly string $basePath = '/',
    ) {
    }

    /**
     * HTML dos grupos pedidos, na ordem em que foram pedidos.
     * @throws CuboException
     */
    public function render(string ...$grupos): string
    {
        $disponiveis = $this->grupos();
        $pedidos = $grupos === [] ? [self::GRUPO_PADRAO] : $grupos;

        $saida = [];

        foreach ($pedidos as $grupo) {
            if (!isset($disponiveis[$grupo])) {
                throw new CuboException(
                    "Grupo de imports '{$grupo}' nao existe em {$this->arquivo}. "
                    . 'Declarados: ' . implode(', ', array_keys($disponiveis)) . '.'
                );
            }

            $saida[] = $this->resolverCaminhos($disponiveis[$grupo]);
        }

        return trim(implode(PHP_EOL, $saida));
    }

    /** @return list<string> nomes dos grupos declarados */
    public function nomes(): array
    {
        return array_keys($this->grupos());
    }

    /**
     * Quebra o arquivo nos marcadores `<!-- @grupo nome -->`.
     * @return array<string, string>
     * @throws CuboException se o arquivo nao existir
     */
    public function grupos(): array
    {
        if ($this->grupos !== null) {
            return $this->grupos;
        }

        if (!is_file($this->arquivo)) {
            // o ImportHelper do v1 devolvia false aqui e a pagina renderizava sem
            // CSS nem JS, calada. Some o arquivo, some a tela: isso tem de gritar
            throw new CuboException("Arquivo de imports nao encontrado: {$this->arquivo}");
        }

        $conteudo = (string) file_get_contents($this->arquivo);

        $partes = preg_split(
            '/<!--\s*@grupo\s+([\w.-]+)\s*-->/i',
            $conteudo,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $grupos = [];
        $inicial = trim((string) array_shift($partes));

        if ($inicial !== '') {
            $grupos[self::GRUPO_PADRAO] = $inicial;
        }

        // o split alterna nome do grupo e conteudo dele
        for ($i = 0; $i + 1 < count($partes); $i += 2) {
            $nome = $partes[$i];
            $html = trim($partes[$i + 1]);

            $grupos[$nome] = isset($grupos[$nome])
                ? $grupos[$nome] . PHP_EOL . $html
                : $html;
        }

        return $this->grupos = $grupos;
    }

    /**
     * Reescreve src/href: prefixa a base da app e carimba a versao.
     */
    private function resolverCaminhos(string $html): string
    {
        return (string) preg_replace_callback(
            '/\b(src|href)\s*=\s*(["\'])(.+?)\2/i',
            function (array $m): string {
                [$tudo, $atributo, $aspa, $caminho] = $m;

                if ($this->ehExterno($caminho)) {
                    return $tudo;
                }

                return $atributo . '=' . $aspa . $this->urlDe($caminho) . $aspa;
            },
            $html
        );
    }

    private function ehExterno(string $caminho): bool
    {
        return str_starts_with($caminho, 'http://')
            || str_starts_with($caminho, 'https://')
            || str_starts_with($caminho, '//')
            || str_starts_with($caminho, 'data:');
    }

    private function urlDe(string $caminho): string
    {
        $relativo = ltrim(explode('?', $caminho, 2)[0], '/');
        $url = rtrim($this->basePath, '/') . '/' . $relativo;

        $emDisco = rtrim($this->publicRoot, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativo);

        // sem o arquivo em disco (asset gerado no deploy, por exemplo) a tag vai
        // sem versao, em vez de a pagina quebrar
        return is_file($emDisco)
            ? $url . '?v=' . filemtime($emDisco)
            : $url;
    }
}
