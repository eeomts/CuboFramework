<?php

namespace Cubo\Auth;

/**
 * Contrato de leitura das chaves de API.
 *
 * A implementacao mora na APP -- e ela que conhece onde as chaves ficam e em qual
 * conexao; o framework so conhece esta interface. Foi assim que a SQLi do v1
 * morreu: a consulta saiu de dentro do Auth.
 *
 * Implementacao esperada na app:
 *
 *     use App\Models\ChaveApi;
 *     use Cubo\Auth\ApiKey;
 *     use Cubo\Auth\ApiKeyRepository;
 *
 *     final class EloquentApiKeyRepository implements ApiKeyRepository
 *     {
 *         public function findActiveByCredentials(string $appId, string $appSecret): ?ApiKey
 *         {
 *             $row = ChaveApi::query()
 *                 ->where('app_id', $appId)
 *                 ->where('app_secret', $appSecret)
 *                 ->where('fk_boolean', 1)
 *                 ->first();
 *
 *             return $row === null
 *                 ? null
 *                 : new ApiKey((int) $row->fk_conta, $row->url_access);
 *         }
 *     }
 *
 * @package Cubo
 * @author v2: Mateus - github.com/eeomts
 */
interface ApiKeyRepository
{
    /**
     * Busca uma chave ATIVA (fk_boolean = 1) pelas credenciais informadas.
     *
     * IMPORTANTE: a implementacao TEM de usar bind (query builder do Eloquent),
     * nunca concatenacao -- as credenciais chegam de um header HTTP.
     *
     * @return ApiKey|null null quando nao existe chave ativa com esse par.
     */
    public function findActiveByCredentials(string $appId, string $appSecret): ?ApiKey;
}
