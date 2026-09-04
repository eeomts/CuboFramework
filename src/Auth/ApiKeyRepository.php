<?php

namespace Cubo\Auth;

/**
 * Contrato de leitura das chaves de API. Quem le a tabela e a app.
 *
 * @package Cubo
 * @author v2: Mateus - github.com/eeomts
 */
interface ApiKeyRepository
{
    /**
     * Busca uma chave ATIVA (fk_boolean = 1) pelo app_id.
     *
     * O segredo fica fora da consulta: a collation padrao do Cubo
     * (utf8mb4_unicode_ci) IGNORA a caixa, entao 'SEGREDO' casaria com 'segredo'
     * e a chave perderia forca em silencio. A comparacao mora no ApiKey.
     *
     * A implementacao TEM de usar bind: o app_id chega de um header HTTP.
     */
    public function findActiveByAppId(string $appId): ?ApiKey;
}
