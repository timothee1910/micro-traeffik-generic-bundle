<?php

namespace Micro\TraeffikGenericBundle\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class AbstractRequestService
{
    private TokenProviderService $tokenProviderService;
    private ?ResponseInterface $lastAuthenticatedRequest = null;
    private bool $disableHttp = false;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function setTokenProviderService(TokenProviderService $tokenProviderService): void
    {
        $this->tokenProviderService = $tokenProviderService;
    }

    public function setParameter(bool|string $disableHttp): void
    {
        $this->disableHttp = 'true' === $disableHttp || true === $disableHttp;
    }

    /**
     * @param array{headers?: array<string,mixed>} $options
     *
     * @return array<string|int,mixed>
     */
    public function authenticatedRequest(string $method, string $url, array $options = []): array
    {
        if ($this->disableHttp) {
            return [];
        }

        if (!array_key_exists('headers', $options)) {
            $options['headers'] = [];
        }

        $options['headers'] = [...[
            'Authorization' => 'Bearer ' . $this->tokenProviderService->getToken(),
        ], ...$options['headers']];

        try {
            $this->lastAuthenticatedRequest = $this->httpClient->request($method, $url, $options);
            if (204 === $this->lastAuthenticatedRequest->getStatusCode()) {
                return [];
            }
            /** @var array<string|int,mixed> */
            $result = json_decode($this->lastAuthenticatedRequest->getContent(), true, 512, JSON_THROW_ON_ERROR);

            return $result;
        } catch (ClientExceptionInterface) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->tokenProviderService->refreshToken();
            $this->lastAuthenticatedRequest = $this->httpClient->request($method, $url, $options);

            /** @var array<string|int,mixed> */
            $result = json_decode($this->lastAuthenticatedRequest->getContent(), true, 512, JSON_THROW_ON_ERROR);

            return $result;
        }
    }

    public function getLastAuthenticatedRequest(): ?ResponseInterface
    {
        return $this->lastAuthenticatedRequest;
    }
}
