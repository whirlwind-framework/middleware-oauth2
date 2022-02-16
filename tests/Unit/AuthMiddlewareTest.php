<?php

namespace Test\Unit;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whirlwind\Infrastructure\Repository\Rest\Exception\ClientException;
use Whirlwind\Middleware\OAuth\AuthMiddleware;
use Whirlwind\Middleware\OAuth\TokenInfo;
use Whirlwind\Middleware\OAuth\TokenInfoNotFoundException;
use Whirlwind\Middleware\OAuth\TokenInfoRepository;

class AuthMiddlewareTest extends TestCase
{
    public function testWithoutAuthorizationHeader(): void
    {
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();
        $request = $requestFactory->createServerRequest('POST', '/some/uri');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $tokenInfoRepository = $this->createMock(TokenInfoRepository::class);
        $middleware = new AuthMiddleware($tokenInfoRepository, $responseFactory);
        $actual = $middleware->process($request, $handler);

        $this->assertEquals(401, $actual->getStatusCode());
    }

    public function testWithWrongScopes(): void
    {
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $request = $requestFactory->createServerRequest('POST', '/some/uri')
            ->withHeader('Authorization', 'Bearer r32rf3rf32');

        $tokenInfoRepository = $this->createMock(TokenInfoRepository::class);
        $tokenInfoRepository->expects($this->once())->method('findByAuthorizationHeader')
            ->willReturn(new TokenInfo('', '', '', '', ['events.delivery' => []]));

        $middleware = (new AuthMiddleware($tokenInfoRepository, $responseFactory))
            ->withRequiredScopes(['trips.example']);
        $actual = $middleware->process($request, $handler);

        $this->assertEquals(403, $actual->getStatusCode());
    }

    public function testWithGlobalScope(): void
    {
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $defaultSuccessResponse = $this->createMock(ResponseInterface::class);

        $defaultSuccessResponse->method('getStatusCode')
            ->willReturn(200);
        $handler->method('handle')
            ->willReturn($defaultSuccessResponse);

        $request = $requestFactory->createServerRequest('POST', '/some/uri')
            ->withHeader('Authorization', 'Bearer r32rf3rf32');

        $tokenInfoRepository = $this->createMock(TokenInfoRepository::class);
        $tokenInfoRepository->expects($this->once())->method('findByAuthorizationHeader')
            ->willReturn(new TokenInfo('', '', '', '', ['events' => []]));

        $middleware = (new AuthMiddleware($tokenInfoRepository, $responseFactory))
            ->withRequiredScopes(['events.example']);
        $actual = $middleware->process($request, $handler);

        $this->assertEquals(200, $actual->getStatusCode());
    }

    public function testWithoutRequiredScopes(): void
    {
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $defaultSuccessResponse = $this->createMock(ResponseInterface::class);

        $defaultSuccessResponse->method('getStatusCode')
            ->willReturn(200);
        $handler->method('handle')
            ->willReturn($defaultSuccessResponse);

        $request = $requestFactory->createServerRequest('POST', '/some/uri')
            ->withHeader('Authorization', 'Bearer r32rf3rf32');

        $tokenInfoRepository = $this->createMock(TokenInfoRepository::class);
        $tokenInfoRepository->expects($this->once())->method('findByAuthorizationHeader')
            ->willReturn(new TokenInfo('', '', '', '', ['events' => []]));

        $middleware = new AuthMiddleware($tokenInfoRepository, $responseFactory);
        $actual = $middleware->process($request, $handler);

        $this->assertEquals(200, $actual->getStatusCode());
    }

    public function testWithClientException(): void
    {
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request = $requestFactory->createServerRequest('POST', '/some/uri')
            ->withHeader('Authorization', 'Bearer r32rf3rf32');

        $exceptionMessage = 'Bad header';
        $tokenInfoRepository = $this->createMock(TokenInfoRepository::class);
        $tokenInfoRepository->expects($this->once())->method('findByAuthorizationHeader')
            ->willThrowException(new ClientException(400, $exceptionMessage));

        $middleware = (new AuthMiddleware($tokenInfoRepository, $responseFactory))
            ->withRequiredScopes(['events.example']);
        $actual = $middleware->process($request, $handler);

        $this->assertEquals(400, $actual->getStatusCode());
        $this->assertEquals($exceptionMessage, $actual->getReasonPhrase());

        $exceptionMessage = 'Not found';
        $tokenInfoRepository = $this->createMock(TokenInfoRepository::class);
        $tokenInfoRepository->expects($this->once())->method('findByAuthorizationHeader')
            ->willThrowException(new TokenInfoNotFoundException($exceptionMessage));

        $middleware = (new AuthMiddleware($tokenInfoRepository, $responseFactory))
            ->withRequiredScopes(['events.example']);
        $actual = $middleware->process($request, $handler);

        $this->assertEquals(404, $actual->getStatusCode());
        $this->assertEquals($exceptionMessage, $actual->getReasonPhrase());
    }

    public function testWithTokenInQuery(): void
    {
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $defaultSuccessResponse = $this->createMock(ResponseInterface::class);

        $defaultSuccessResponse->method('getStatusCode')
            ->willReturn(200);
        $handler->method('handle')
            ->willReturn($defaultSuccessResponse);

        $request = $requestFactory->createServerRequest('POST', '/some/uri')
            ->withQueryParams(['access_token' => 'r32rf3rf32']);

        $tokenInfoRepository = $this->createMock(TokenInfoRepository::class);
        $tokenInfoRepository->expects($this->once())->method('findByAuthorizationHeader')
            ->willReturn(new TokenInfo('', '', '', '', ['events' => []]));

        $middleware = (new AuthMiddleware($tokenInfoRepository, $responseFactory))
            ->withRequiredScopes(['events.example']);
        $actual = $middleware->process($request, $handler);

        $this->assertEquals(200, $actual->getStatusCode());
    }

    public function testWithTokenInBody(): void
    {
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $defaultSuccessResponse = $this->createMock(ResponseInterface::class);

        $defaultSuccessResponse->method('getStatusCode')
            ->willReturn(200);
        $handler->method('handle')
            ->willReturn($defaultSuccessResponse);

        $request = $requestFactory->createServerRequest('POST', '/some/uri')
            ->withParsedBody(['access_token' => 'r32rf3rf32']);

        $tokenInfoRepository = $this->createMock(TokenInfoRepository::class);
        $tokenInfoRepository->expects($this->once())->method('findByAuthorizationHeader')
            ->willReturn(new TokenInfo('', '', '', '', ['events.example' => []]));
        $middleware = (new AuthMiddleware($tokenInfoRepository, $responseFactory))
            ->withRequiredScopes(['events.example']);
        $actual = $middleware->process($request, $handler);

        $this->assertEquals(200, $actual->getStatusCode());
    }
}
