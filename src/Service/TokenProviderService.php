<?php

namespace Micro\TraeffikGenericBundle\Service;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TokenProviderService
{
    private readonly HttpClientInterface $keycloakClientFinal;
    private string $token;
    private ?RedisAdapter $redis = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        HttpClientInterface $keycloakClient,
        string $keycloakBaseUrl,
        private string $keycloakClientId,
        private string $keycloakClientSecret,
        public string $redisUrl,
        private readonly string $redisKey
    ) {
        $this->keycloakClientFinal = $keycloakClient->withOptions([
            'base_uri' => $keycloakBaseUrl,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    public function getToken(): string
    {
        $this->connectRedis();
        $token = '';
        if ($this->requestStack->getCurrentRequest()) {
            $request = $this->requestStack->getCurrentRequest();
            $token = $request->headers->get('Authorization');

            if (!empty($token)) {
                return str_replace('Bearer ', '', $token);
            }
        }

        if (!empty($this->$token)) {
            return $this->$token;
        }

        /** @var string */
        $token = $this->redis?->get($this->redisKey, fn () => $this->requestToken());

        $this->token = $token;

        return $this->token;
    }

    public function refreshToken(): string
    {
        $this->connectRedis();

        $this->redis?->delete($this->redisKey);

        /** @var string */
        $token = $this->redis?->get($this->redisKey, fn () => $this->requestToken());

        $this->token = $token;

        return $this->token;
    }

    public function requestToken(): string
    {
        $request = $this->keycloakClientFinal->request('POST', 'protocol/openid-connect/token/', [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->keycloakClientId,
                'client_secret' => $this->keycloakClientSecret,
            ],
        ]);

        /** @var array{access_token: string} */
        $response = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $response['access_token'];
    }

    private function connectRedis(): void
    {
        if (!$this->redis) {
            $this->redis = new RedisAdapter(RedisAdapter::createConnection($this->redisUrl));
        }
    }

    public function setClientKeycloak(string $keycloakClientId, string $keycloakClientSecret): void
    {
        $this->keycloakClientId = $keycloakClientId;
        $this->keycloakClientSecret = $keycloakClientSecret;
    }
}
