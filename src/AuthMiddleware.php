<?php

declare(strict_types=1);

namespace Whirlwind\Middleware\OAuth;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whirlwind\Middleware\OAuth\Infrastructure\Oauth\Exception\TokenInfoNotFoundException;
use Whirlwind\Middleware\OAuth\Infrastructure\Oauth\TokenInfo;
use Whirlwind\Infrastructure\Repository\Rest\Exception\ClientException;
use Whirlwind\Infrastructure\Repository\Rest\Exception\ServerException;
use Whirlwind\Middleware\OAuth\Infrastructure\Repository\TokenInfoRepository;

final class AuthMiddleware implements MiddlewareInterface
{
    private array $scopes = [];
    private TokenInfoRepository $tokenInfoRepository;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(
        TokenInfoRepository $tokenInfoRepository,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->tokenInfoRepository = $tokenInfoRepository;
    }

    public function withRequiredScopes(array $scopes): self
    {
        $clone = clone $this;
        $clone->scopes = $clone->formatScopes($scopes);

        return $clone;
    }

    private function formatScopes(array $scopes): array
    {
        return array_map(static fn ($scope) => \strtolower($scope), $scopes);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $tokenHeader = $this->getTokenHeader($request);

        if ($tokenHeader === '') {
            return $this->responseFactory->createResponse(401);
        }

        try {
            $tokenInfo = $this->tokenInfoRepository->findByAuthorizationHeader($tokenHeader);
        } catch (ClientException | ServerException $e) {
            return $this->responseFactory->createResponse(
                $e->getHttpCode(),
                \str_replace(["\n","\r"], '', $e->getMessage())
            );
        } catch (TokenInfoNotFoundException $e) {
            return $this->responseFactory->createResponse(
                404,
                \str_replace(["\n","\r"], '', $e->getMessage())
            );
        }

        if (!$this->isScopesValid($tokenInfo)) {
            return $this->responseFactory->createResponse(403);
        }

        return $handler->handle($request);
    }

    private function getTokenHeader(ServerRequestInterface $request): string
    {
        $tokenHeader = $request->getHeaderLine('Authorization');

        if ($tokenHeader !== '') {
            return $tokenHeader;
        }

        $tokenHeader = $request->getQueryParams()['access_token'] ?? '';

        if ($tokenHeader !== '') {
            return 'Bearer ' . $tokenHeader;
        }

        $tokenHeader = $request->getParsedBody()['access_token'] ?? '';

        if ($tokenHeader !== '') {
            return 'Bearer ' . $tokenHeader;
        }

        return '';
    }

    private function isScopesValid(TokenInfo $tokenInfo): bool
    {
        if ($this->scopes === []) {
            return true;
        }

        foreach ($this->scopes as $scope) {
            if (
                isset($tokenInfo->getScopes()[$scope])
                || isset($tokenInfo->getScopes()[\explode('.', $scope)[0]])
            ) {
                return true;
            }
        }

        return false;
    }
}
