<?php

declare(strict_types=1);

namespace Whirlwind\Middleware\OAuth\Infrastructure\Repository;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;
use Whirlwind\Domain\Validation\Exception\ValidateException;
use Whirlwind\Infrastructure\Hydrator\UnderscoreToCamelCaseHydrator;
use Whirlwind\Middleware\OAuth\Infrastructure\Oauth\Exception\TokenInfoNotFoundException;
use Whirlwind\Middleware\OAuth\Infrastructure\Oauth\TokenInfo;
use Whirlwind\Infrastructure\Repository\Rest\Repository;

class TokenInfoRepository extends Repository
{
    public function __construct(
        UnderscoreToCamelCaseHydrator $hydrator,
        UriFactoryInterface $uriFactory,
        RequestFactoryInterface $requestFactory,
        ClientInterface $client,
        string $modelClass,
        string $endpoint
    ) {
        parent::__construct($hydrator, $uriFactory, $requestFactory, $client, $modelClass, $endpoint);
    }

    /**
     * @param string $accessToken
     * @return TokenInfo
     * @throws \Throwable
     */
    public function findByAccessToken(string $accessToken): TokenInfo
    {
        $this->addHeader('Accept', 'application/json');
        $uri = $this->uriFactory->createUri($this->endpoint)->withQuery(\http_build_query([
            'access_token' => $accessToken,
        ]));
        $data = $this->request('GET', $uri);

        return $this->hydrator->hydrate($this->modelClass, $data['body']);
    }

    protected function createException(ResponseInterface $responseWithException): \Throwable
    {
        switch ($responseWithException->getStatusCode()) {
            case 422:
                $data = \json_decode($responseWithException->getBody()->getContents(), true);
                return new ValidateException($data);
            case 404:
                $data = \json_decode($responseWithException->getBody()->getContents(), true);
                return new TokenInfoNotFoundException($data['message'] ?? 'Token info not found');
            default:
                return parent::createException($responseWithException);
        }
    }

    /**
     * @param string $authHeader
     * @return TokenInfo
     * @throws \Throwable
     */
    public function findByAuthorizationHeader(string $authHeader): TokenInfo
    {
        $this->addToken($authHeader);
        $this->addHeader('Accept', 'application/json');
        $uri = $this->uriFactory->createUri($this->endpoint);
        $data = $this->request('GET', $uri);

        return $this->hydrator->hydrate($this->modelClass, $data['body']);
    }
}
