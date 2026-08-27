<?php

namespace Cubo\Tools;

/**
 * Extração de ID e montagem de URL de vídeos (YouTube e Vimeo).
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Video
{
    /** Aceita watch, youtu.be, embed, shorts e live. */
    public static function youtubeId(string $url): ?string
    {
        $pattern = '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:[^&\s]*&)*v=|embed/|v/|shorts/|live/))([A-Za-z0-9_-]{11})~i';

        return preg_match($pattern, $url, $m) ? $m[1] : null;
    }

    public static function vimeoId(string $url): ?string
    {
        return preg_match('~vimeo\.com/(?:[^/\s]+/)*(\d+)~i', $url, $m) ? $m[1] : null;
    }

    /** Detecta a plataforma pela URL e extrai o ID. */
    public static function id(string $url): ?string
    {
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return self::youtubeId($url);
        }

        if (str_contains($url, 'vimeo.com')) {
            return self::vimeoId($url);
        }

        return null;
    }

    /** ID de 11 caracteres nao-numerico e YouTube; o resto e Vimeo. */
    public static function url(string $id): string
    {
        return (strlen($id) === 11 && !is_numeric($id))
            ? 'https://www.youtube.com/watch?v=' . $id
            : 'https://vimeo.com/' . $id;
    }
}
