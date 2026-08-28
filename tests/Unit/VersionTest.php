<?php

namespace Cubo\Tests\Unit;

use Cubo\Console\Paths;
use Cubo\Cubo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A versao passou a ser controlada pelo arquivo VERSION, e nao por constante no
 * codigo. O risco e o arquivo nao viajar junto no `cubo build`.
 */
#[CoversClass(Cubo::class)]
final class VersionTest extends TestCase
{
    private function arquivo(): string
    {
        return (new Paths(dirname(__DIR__, 2)))->versionFile();
    }

    public function testVersionSaiDoArquivoVERSION(): void
    {
        $noDisco = trim((string) file_get_contents($this->arquivo()));

        $this->assertSame($noDisco, Cubo::version());
    }

    public function testOArquivoVERSIONExisteNaRaizDoFramework(): void
    {
        $this->assertFileExists($this->arquivo());
    }

    /** Le uma vez so: version() e chamada em todo help e nao deve bater no disco sempre. */
    public function testVersionEhCacheada(): void
    {
        $this->assertSame(Cubo::version(), Cubo::version());
    }

    public function testVersionNaoVoltaVazia(): void
    {
        $this->assertNotSame('', Cubo::version());
    }

    /** O caminho lido e o mesmo que o build copia -- se divergirem, o projeto gerado perde a versao. */
    public function testOCaminhoLidoEhOMesmoQueOBuildCopia(): void
    {
        $lidoPeloCubo = dirname((new \ReflectionClass(Cubo::class))->getFileName())
            . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'VERSION';

        $this->assertSame(
            realpath($this->arquivo()),
            realpath($lidoPeloCubo)
        );
    }
}
