<?php

declare(strict_types=1);

namespace Virgile\Routing\Tests;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Virgile\Routing\Exceptions\InvalidHandlerFormatException;
use Virgile\Routing\Exceptions\InvalidMiddlewareFormatException;
use Virgile\Routing\Route;
use Virgile\Routing\SignatureInterface;

class RouteTest extends TestCase
{
    public function test_can_be_instantiated(): void
    {
        $signature = $this->createMock(SignatureInterface::class);
        $route = new Route($signature, ['Controller', 'index']);
        $this->assertEquals(['Controller', 'index'], $route->getHandler());
        $this->assertSame($signature, $route->getSignature());
    }

    public function test_can_be_instantiated_with_valid_middlewares(): void
    {
        $signature = $this->createMock(SignatureInterface::class);
        $closureMiddleware = function (): void {};
        $route = new Route($signature, ['Controller', 'index'], ['App\\Middleware\\Auth', $closureMiddleware]);
        $this->assertEquals(['App\\Middleware\\Auth', $closureMiddleware], $route->getMiddlewares());
        $this->assertEquals(['Controller', 'index'], $route->getHandler());
        $this->assertSame($signature, $route->getSignature());
    }

    public function test_throws_exception_on_invalid_middleware_format(): void
    {
        $this->expectException(InvalidMiddlewareFormatException::class);
        $signature = $this->createMock(SignatureInterface::class);
        /** @phpstan-ignore-next-line */
        new Route($signature, ['Controller', 'index'], [123]);
    }

    public function test_with_middleware_returns_new_instance(): void
    {
        $signature = $this->createMock(SignatureInterface::class);
        $oldMiddlewares = ['OldMiddleware'];
        $newMiddleware = function (): void {};
        $route = new Route($signature, ['Controller', 'index'], $oldMiddlewares);
        $newRoute = $route->withMiddleware($newMiddleware);

        $this->assertEquals($oldMiddlewares, $route->getmiddlewares());
        $this->assertEquals(['OldMiddleware', $newMiddleware], $newRoute->getMiddlewares());
        $this->assertNotSame($route, $newRoute);
    }

    public function test_with_middlewares_returns_new_instance(): void
    {
        $signature = $this->createMock(SignatureInterface::class);
        $oldMiddlewares = ['OldMiddleware'];
        $newMiddlewares = ['NewMiddleware'];
        $route = new Route($signature, ['Controller', 'index'], $oldMiddlewares);
        $newRoute = $route->withMiddlewares($newMiddlewares);

        $this->assertEquals($oldMiddlewares, $route->getMiddlewares());
        $this->assertEquals($newMiddlewares, $newRoute->getMiddlewares());
        $this->assertNotSame($route, $newRoute);
    }

    public function test_get_signature(): void
    {
        $signature = $this->createMock(SignatureInterface::class);
        $route = new Route($signature, ['Controller', 'index']);
        $this->assertSame($signature, $route->getSignature());
    }

    public function test_get_handler_as_valid_array(): void
    {
        $signature = $this->createMock(SignatureInterface::class);
        $handler = ['App\\Controllers\\HomeController', 'index'];
        $route = new Route($signature, $handler);

        $this->assertEquals($handler, $route->getHandler());
        $this->assertIsArray($route->getHandler());
    }

    #[DataProvider('invalidHandlerProvider')]
    public function test_throws_exception_on_invalid_handler_format(mixed $invalidHandler): void
    {
        $this->expectException(InvalidHandlerFormatException::class);
        $signature = $this->createMock(SignatureInterface::class);
        /** @phpstan-ignore-next-line */
        new Route($signature, $invalidHandler);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidHandlerProvider(): array
    {
        return [
            'empty array' => [[]],
            'too few items' => [['Controller']],
            'too much items' => [['Controller', 'index', 'extra']],
            'associative array' => [['controller' => 'Controller', 'method' => 'index']],
            'first item bad type' => [[123, 'index']],
            'second item bad type' => [['Controller', 456]],
            'first item empty' => [['', 'index']],
            'second item empty' => [['Controller', '']],
        ];
    }

    public function test_get_handler_as_closure(): void
    {
        $signature = $this->createMock(SignatureInterface::class);
        $handler = fn(): string => 'response';
        $route = new Route($signature, $handler);

        $this->assertSame($handler, $route->getHandler());
        $this->assertInstanceOf(Closure::class, $route->getHandler());
    }

    public function test_get_middlewares(): void
    {
        $signature = $this->createMock(SignatureInterface::class);
        $middlewares = ['AuthMiddleware', 'LogMiddleware'];
        $route = new Route($signature, ['Controller', 'index'], $middlewares);
        $this->assertEquals($middlewares, $route->getMiddlewares());
    }
}
