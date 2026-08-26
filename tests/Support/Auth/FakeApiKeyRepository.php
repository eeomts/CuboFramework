<?php

namespace Cubo\Tests\Support\Auth;

use Cubo\Auth\ApiKey;
use Cubo\Auth\ApiKeyRepository;

/**
 * Repositorio de chaves em memoria, para testar o Auth sem banco.
 *
 * Registra as credenciais recebidas em $calls para que os testes possam provar
 * que o valor do header chegou CRU ao repositorio -- e portanto que a defesa
 * contra SQLi mora no bind, nao em alguma limpeza pelo caminho.
 */
final class FakeApiKeyRepository implements ApiKeyRepository
{
    /** @var list<array{appId: string, appSecret: string}> */
    public array $calls = [];

    public function __construct(private readonly ?ApiKey $key = null)
    {
    }

    public function findActiveByCredentials(string $appId, string $appSecret): ?ApiKey
    {
        $this->calls[] = ['appId' => $appId, 'appSecret' => $appSecret];

        return $this->key;
    }
}
