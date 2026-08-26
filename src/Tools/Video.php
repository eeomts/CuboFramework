<?php

namespace Cubo\Tools;

/**
 * Extração de ID e montagem de URL de vídeos (YouTube e Vimeo).
 *
 * Quinta parte da explosão da God Class Cubo_Tools.
 * O regex do v1 (getVideoId) estava corompido: tinha entidades HTML (&lt; &gt;) coladas no padrão e espaços
 * soltos na alternância, então a parte de iframe/embed nunca funcionou. Aqui os
 * padrões foram reescritos limpos. Veja o GUIA DE MIGRACAO no fim do arquivo.
 *
 * @package Cubo
 * @author v1: Cristiano (Cubo_Tools)
 * @author v2: Mateus - github.com/eeomts
 */
final class Video
{
    /**
     * Extrai o ID de 11 caracteres de uma URL do YouTube (watch, youtu.be,
     * embed, shorts, live). Retorna null se não encontrar.
     */
    public static function youtubeId(string $url): ?string
    {
        $pattern = '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:[^&\s]*&)*v=|embed/|v/|shorts/|live/))([A-Za-z0-9_-]{11})~i';

        return preg_match($pattern, $url, $m) ? $m[1] : null;
    }

    /**
     * Extrai o ID numérico de uma URL do Vimeo. Retorna null se não encontrar.
     */
    public static function vimeoId(string $url): ?string
    {
        return preg_match('~vimeo\.com/(?:[^/\s]+/)*(\d+)~i', $url, $m) ? $m[1] : null;
    }

    /**
     * Detecta a plataforma pela URL e extrai o ID. (ex-getVideoId)
     * Retorna null quando não é YouTube nem Vimeo.
     */
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

    /**
     * Monta a URL a partir de um ID. (ex-getUrlVideo)
     * Heurística do v1: ID de 11 caracteres não-numérico é YouTube; caso
     * contrário, Vimeo. (YouTube agora em https.)
     */
    public static function url(string $id): string
    {
        return (strlen($id) === 11 && !is_numeric($id))
            ? 'https://www.youtube.com/watch?v=' . $id
            : 'https://vimeo.com/' . $id;
    }
}

/*
 * GUIA DE MIGRACAO - Cubo_Tools (video) -> Cubo\Tools\Video
 *
 * RENOMEADOS
 *   getVideoId -> id
 *   getUrlVideo -> url
 *
 * NOVOS
 *   youtubeId / vimeoId -- extracao direta por plataforma
 *
 * MUDOU
 *   id -- devolve null (era false) quando nao acha
 *   url -- YouTube em https
 */
