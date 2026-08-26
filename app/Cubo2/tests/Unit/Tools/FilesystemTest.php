<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Filesystem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Filesystem::class)]
final class FilesystemTest extends TestCase
{
    public function testSomaTamanhoRecursivoDaPasta(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cubo_fs_' . uniqid();
        $sub = $base . DIRECTORY_SEPARATOR . 'sub';
        mkdir($sub, 0777, true);

        try {
            // bytes conhecidos: 100 + 250 na raiz, 50 no subdiretorio = 400
            file_put_contents($base . DIRECTORY_SEPARATOR . 'a.txt', str_repeat('a', 100));
            file_put_contents($base . DIRECTORY_SEPARATOR . 'b.txt', str_repeat('b', 250));
            file_put_contents($sub . DIRECTORY_SEPARATOR . 'c.txt', str_repeat('c', 50));

            $this->assertSame(400, Filesystem::getSizeFolder($base));
        } finally {
            @unlink($sub . DIRECTORY_SEPARATOR . 'c.txt');
            @unlink($base . DIRECTORY_SEPARATOR . 'a.txt');
            @unlink($base . DIRECTORY_SEPARATOR . 'b.txt');
            @rmdir($sub);
            @rmdir($base);
        }
    }

    public function testPastaInexistenteRetornaZero(): void
    {
        $this->assertSame(0, Filesystem::getSizeFolder('/caminho/que/nao/existe'));
    }
}
