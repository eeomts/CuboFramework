<?php

namespace Cubo\Auth;

/**
 * Contrato de leitura das chaves de API.
 *
 * Implementacao esperada na app:
 *
 *     use App\Models\ChaveApi;
 *     use Cubo\Auth\ApiKey;
 *     use Cubo\Auth\ApiKeyRepository;
 *
 *     final class EloquentApiKeyRepository implements ApiKeyRepository
 *     {
 *         public function findActiveByAppId(string $appId): ?ApiKey
 *         {
 *             $row = ChaveApi::query()
 *                 ->where('app_id', $appId)
 *                 ->where('fk_boolean', 1)
 *                 ->first();
 *
 *             return $row === null
 *                 ? null
 *                 : new ApiKey((int) $row->fk_conta, $row->app_secret, $row->url_access);
 *         }
 *     }
 *
 * @package Cubo
 * @author v2: Mateus - github.com/eeomts
 */
interface ApiKeyRepository
{
    /**
     * Busca uma chave ATIVA (fk_boolean = 1) pelo app_id.
     *
     * O segredo ficou de fora da consulta de proposito. Compara-lo em SQL tinha
     * tres problemas: exigia guarda-lo em texto puro (um dump do banco entregava
     * todas as chaves), a comparacao nao era em tempo constante, e a collation
     * padrao do Cubo (utf8mb4_unicode_ci) IGNORA a caixa -- entao 'SEGREDO'
     * casava com 'segredo' e a chave perdia forca em silencio.
     *
     * IMPORTANTE: a implementacao TEM de usar bind (query builder do Eloquent),
     * nunca concatenacao -- o app_id chega de um header HTTP.
     *
     * @return ApiKey|null null quando nao existe chave ativa com esse app_id.
     */
    public function findActiveByAppId(string $appId): ?ApiKey;
}
