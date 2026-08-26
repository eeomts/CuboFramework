<?php

namespace Cubo\Tests\Unit\Tools;

use Cubo\Tools\Video;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Video::class)]
final class VideoTest extends TestCase
{
    #[DataProvider('provedorYoutube')]
    public function testYoutubeId(string $url, ?string $esperado): void
    {
        $this->assertSame($esperado, Video::youtubeId($url));
    }

    public static function provedorYoutube(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch params' => ['https://youtube.com/watch?feature=x&v=dQw4w9WgXcQ&t=1', 'dQw4w9WgXcQ'],
            'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'sem id' => ['https://www.youtube.com/', null],
        ];
    }

    #[DataProvider('provedorVimeo')]
    public function testVimeoId(string $url, ?string $esperado): void
    {
        $this->assertSame($esperado, Video::vimeoId($url));
    }

    public static function provedorVimeo(): array
    {
        return [
            'simples' => ['https://vimeo.com/123456789', '123456789'],
            'canal' => ['https://vimeo.com/channels/staffpicks/123456789', '123456789'],
            'sem id' => ['https://vimeo.com/', null],
        ];
    }

    public function testIdDetectaAPlataforma(): void
    {
        $this->assertSame('dQw4w9WgXcQ', Video::id('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('123456789', Video::id('https://vimeo.com/123456789'));
        $this->assertNull(Video::id('https://exemplo.com/qualquer'));
    }

    public function testUrlReconstroiPelaHeuristica(): void
    {
        // 11 chars não-numérico => YouTube
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', Video::url('dQw4w9WgXcQ'));
        // numérico => Vimeo
        $this->assertSame('https://vimeo.com/123456789', Video::url('123456789'));
    }

    public function testIdEUrlSaoConsistentesParaYoutube(): void
    {
        $id = Video::id('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertSame('dQw4w9WgXcQ', $id);
        $this->assertSame('dQw4w9WgXcQ', Video::id(Video::url($id)));
    }
}
