<?php

declare(strict_types=1);

namespace Whirlwind\Middleware\OAuth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whirlwind\Infrastructure\Http\Exception\ForbiddenHttpException;
use Whirlwind\Infrastructure\Http\Exception\UnauthorizedException;
use Whirlwind\Infrastructure\Http\Exception\HttpException;
use Whirlwind\Infrastructure\Repository\Rest\Exception\ClientException;
use Whirlwind\Infrastructure\Repository\Rest\Exception\ServerException;

class AuthMiddleware implements MiddlewareInterface
{
    private array $scopes = [];
    private TokenInfoRepository $tokenInfoRepository;

    public function __construct(TokenInfoRepository $tokenInfoRepository) {
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
        return \array_map(static fn ($scope) => \strtolower($scope), $scopes);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            if ($header = $request->getHeaderLine('Authorization')) {
                $tokenInfo = $this->tokenInfoRepository->findByAuthorizationHeader($header);
            } else {
                $token = 'get' === \strtolower($request->getMethod())
                    ? ($request->getQueryParams()['access_token'] ?? '')
                    : ($request->getParsedBody()['access_token'] ?? '');
                $tokenInfo =  $this->tokenInfoRepository->findByAccessToken($token);
            }
        } catch (ClientException | ServerException $e) {
            throw new HttpException(
                $e->getHttpCode(),
                \str_replace(["\n","\r"], '', $e->getMessage())
            );
        } catch (\Throwable $e) {
            throw new UnauthorizedException($e->getMessage());
        }

        if (!$this->isScopesValid($tokenInfo)) {
            throw new ForbiddenHttpException('Forbidden');
        }

        return $handler->handle($request->withAttribute('oauth2-token-info', $tokenInfo));
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
