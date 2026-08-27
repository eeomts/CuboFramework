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
 * Monta em dist/ o molde que o init copia: esqueleto + framework + vendor.
 *
 * O composer roda DENTRO do dist, ja no formato final do projeto, para o
 * autoloader nascer apontando pra cubo/src em vez de precisar de remendo.
 */
final class BuildCommand implements Command
{
    public function __construct(private readonly Paths $paths = new Paths('')) {}

    public static function name(): string
    {
        return 'build';
    }

    public static function description(): string
    {
        return 'Monta o molde em dist/ (esqueleto + framework + vendor)';
    }

    public function handle(Input $input, Output $output): int
    {
        $skeletonName = (string) $input->option('skeleton', 'app');
        $skeleton = $this->paths->skeleton($skeletonName);

        if (!is_dir($skeleton)) {
            throw new CuboException(
                "Esqueleto '{$skeletonName}' nao existe. Disponiveis: "
                . implode(', ', $this->paths->skeletons())
            );
        }

        $dist = $this->paths->dist();

        $output->line("Limpando {$dist}");
        Filesystem::removeDirectory($dist);

        $output->line("Copiando esqueleto '{$skeletonName}'");
        Filesystem::copyDirectory($skeleton, $dist);

        $output->line('Copiando o framework para cubo/src');
        Filesystem::copyDirectory($this->paths->src(), $dist . DIRECTORY_SEPARATOR . 'cubo' . DIRECTORY_SEPARATOR . 'src');

        $output->line('Instalando dependencias de producao');
        $code = $this->composerInstall($dist, $output);

        if ($code !== 0) {
            $output->error('composer install falhou; dist/ ficou incompleto');

            return Kernel::EXIT_FAILURE;
        }

        $output->line('Molde pronto em ' . $dist);

        return Kernel::EXIT_SUCCESS;
    }

    private function composerInstall(string $dist, Output $output): int
    {
        $command = sprintf(
            'composer install --no-dev --no-interaction --quiet --working-dir=%s 2>&1',
            escapeshellarg($dist)
        );

        exec($command, $lines, $code);

        foreach ($lines as $line) {
            $output->line('  ' . $line);
        }

        return $code;
    }
}
