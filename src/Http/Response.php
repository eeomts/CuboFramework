<?php

namespace Cubo\Http;

/**
 * Construtor de respostas HTTP (status, headers, body).
 *
 * @package Cubo
 */
class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    /**
     * Factory para resposta JSON.
     *
     * @throws \JsonException se o dado nao for serializavel; sem isso o
     *                        json_encode devolveria false e o corpo sairia vazio
     *                        com status 200
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Content-Type'] = 'application/json';
        $r->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $r;
    }

    /** Factory para redirect. */
    public static function redirect(string $url, int $status = 302): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Location'] = $url;
        return $r;
    }

    /** Factory para resposta com string simples. */
    public static function text(string $text, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->body = $text;
        return $r;
    }

    /** Factory para resposta HTML. */
    public static function html(string $html, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Content-Type'] = 'text/html; charset=utf-8';
        $r->body = $html;
        return $r;
    }

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function body(string $content): self
    {
        $this->body = $content;
        return $this;
    }

    public function send(): void
    {
        // corpo ja emitido significa cabecalho ja enviado; insistir so produz
        // "headers already sent" e nao muda nada
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->body;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
