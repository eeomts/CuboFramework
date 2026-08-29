<?php

namespace Cubo\Tests\Support\Auth;

use Cubo\Auth\ApiKey;
use Cubo\Auth\ApiKeyRepository;

/**
 * Repositorio de chaves em memoria, para testar o Auth sem banco.
 *
 * Registra os app_id recebidos em $calls para que os testes possam provar que o
 * valor do header chegou CRU ao repositorio -- e portanto que a defesa contra
 * SQLi mora no bind, nao em alguma limpeza pelo caminho.
 *
 * O segredo nao aparece aqui de proposito: ele nao entra mais na consulta.
 */
final class FakeApiKeyRepository implements ApiKeyRepository
{
    /** @var list<string> app_id de cada consulta, na ordem */
    public array $calls = [];

    public function __construct(private readonly ?ApiKey $key = null)
    {
    }

    public function findActiveByAppId(string $appId): ?ApiKey
    {
        $this->calls[] = $appId;

        return $this->key;
    }
}
