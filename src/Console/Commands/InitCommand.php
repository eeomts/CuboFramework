<?php

namespace Cubo\Console\Commands;

use Cubo\Console\Command;
use Cubo\Console\Input;
use Cubo\Console\Kernel;
use Cubo\Console\Output;
use Cubo\Console\Paths;
use Cubo\Exceptions\CuboException;
use Cubo\Tools\Filesystem;

/**
 * cubo init <nome>  cria a pasta <nome> e brota o projeto dentro
 * cubo init .       brota na pasta atual
 */
final class InitCommand implements Command
{
    public function __construct(private readonly Paths $paths = new Paths('')) {}

    public static function name(): string
    {
        return 'init';
    }

    public static function description(): string
    {
        return 'Cria um projeto Cubo (use . para a pasta atual)';
    }

    public function handle(Input $input, Output $output): int
    {
        $dist = $this->paths->dist();

        if (!is_dir($dist)) {
            throw new CuboException("Molde ausente em {$dist}. Rode 'cubo build' primeiro.");
        }

        $alvo = $input->argument(0, '.');
        $destino = $this->resolveDestination((string) $alvo);
        $nome = basename($destino);

        if (!Filesystem::isEmptyDirectory($destino) && !$input->hasOption('force')) {
            throw new CuboException(
                "A pasta '{$destino}' nao esta vazia. Use --force para escrever mesmo assim."
            );
        }

        $output->line("Criando projeto em {$destino}");
        Filesystem::copyDirectory($dist, $destino);
        $this->renamePackage($destino, $nome);

        $output->line('');
        $output->line("Pronto. O projeto '{$nome}' esta autocontido: nao precisa de composer.");
        $output->line('Aponte a raiz do servidor para public/ e ajuste config/config.ini.');

        return Kernel::EXIT_SUCCESS;
    }

    /** O ponto significa a pasta atual; qualquer outro nome vira subpasta. */
    private function resolveDestination(string $alvo): string
    {
        $cwd = (string) getcwd();

        if ($alvo === '.' || $alvo === '') {
            return $cwd;
        }

        $destino = $cwd . DIRECTORY_SEPARATOR . $alvo;
        Filesystem::makeDirectory($destino);

        return $destino;
    }

    private function renamePackage(string $destino, string $nome): void
    {
        $arquivo = $destino . DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_file($arquivo)) {
            return;
        }

        $conteudo = (string) file_get_contents($arquivo);
        $slug = strtolower(preg_replace('/[^A-Za-z0-9_.-]/', '-', $nome) ?? 'app');

        file_put_contents(
            $arquivo,
            str_replace('"name": "cubo/app"', '"name": "' . $slug . '/' . $slug . '"', $conteudo)
        );
    }
}
