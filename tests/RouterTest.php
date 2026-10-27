<?php

declare(strict_types=1);

namespace Virgile\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Virgile\Routing\Exceptions\InvalidMiddlewareFormatException;
use Virgile\Routing\Exceptions\NodeNotFoundException;
use Virgile\Routing\Exceptions\RouteNotFoundException;
use Virgile\Routing\Route;
use Virgile\Routing\Router;
use Virgile\Routing\SignatureInterface;

class RouterTest extends TestCase
{
    public function test_can_be_instantiated(): void
    {
        $router = new Router();
        $this->assertInstanceOf(Router::class, $router);
    }

    public function test_can_group_routes_with_prefix(): void
    {
        $router = new Router();
        $groupSignature = $this->createDummySignature(['api', 'v1']);

        $handler = ['PostController', 'index'];
        $router->group($groupSignature, function (Router $r): void {
            $r->add($this->createDummySignature(['posts']), ['PostController', 'index']);
        });

        $matchSignature = $this->createDummySignature(['api', 'v1', 'posts']);
        $route = $router->match($matchSignature);
        $this->assertEquals($handler, $route->getHandler());
    }

    public function test_can_nest_groups_recursively(): void
    {
        $router = new Router();
        $router->group($this->createDummySignature(['api']), function (Router $r): void {
            $r->group($this->createDummySignature(['v1']), function (Router $r2): void {
                $r2->add($this->createDummySignature(['users']), ['UserController', 'index'], ['RouteMiddleware']);
            }, ['V1Middleware']);
        }, ['ApiMiddleware']);

        $matchSignature = $this->createDummySignature(['api', 'v1', 'users']);
        $route = $router->match($matchSignature);
        $this->assertEquals(['ApiMiddleware', 'V1Middleware', 'RouteMiddleware'], $route->getMiddlewares());
    }

    public function test_can_scope_controller(): void
    {
        $router = new Router();
        $controllerFQCN = 'App\\Controllers\\UserController';
        $router->controller($controllerFQCN, function (Router $r): void {
            $r->add($this->createDummySignature(['profile']), 'show');
        });

        $matchSignature = $this->createDummySignature(['profile']);
        $route = $router->match($matchSignature);
        $this->assertEquals([$controllerFQCN, 'show'], $route->getHandler());
    }

    public function test_can_combine_group_and_controller_scope(): void
    {
        $router = new Router();
        $router->group($this->createDummySignature(['api']), function (Router $r): void {
            $r->controller('App\\Controllers\\Api\\UserController', function (Router $r2): void {
                $r2->add($this->createDummySignature(['profile']), 'show');
            });
        });

        $matchSignature = $this->createDummySignature(['api', 'profile']);
        $route = $router->match($matchSignature);
        $this->assertEquals(['App\\Controllers\\Api\\UserController', 'show'], $route->getHandler());
    }

    public function test_throws_exception_on_invalid_middleware_format(): void
    {
        $this->expectException(InvalidMiddlewareFormatException::class);

        $router = new Router();
        $routeSignature = $this->createDummySignature(['users']);
        /** @phpstan-ignore-next-line */
        $router->add($routeSignature, ['UserController', 'index'], [123]);
    }

    public function test_throws_exception_on_invalid_group_middleware_format(): void
    {
        $this->expectException(InvalidMiddlewareFormatException::class);

        $router = new Router();
        $signature = $this->createDummySignature(['api']);
        $callback = function (Router $r): void {};
        /** @phpstan-ignore-next-line */
        $router->group($signature, $callback, [123]);
    }

    public function test_can_match_static_route(): void
    {
        $router = new Router();
        $signature = $this->createDummySignature(['users']);

        $handler = ['UserController', 'index'];
        $router->add($signature, $handler);
        $route = $router->match($signature);
        $this->assertEquals($handler, $route->getHandler());
    }

    public function test_throws_exception_when_route_not_found(): void
    {
        $this->expectException(NodeNotFoundException::class);

        $router = new Router();
        $signature = $this->createDummySignature(['unknown']);
        $router->match($signature);
    }

    public function test_throws_exception_on_partial_route_match(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $router = new Router();
        $router->add($this->createDummySignature(['users', 'profile']), ['UserController', 'profile']);
        $router->match($this->createDummySignature(['users']));
    }

    public function test_prioritizes_static_route_over_dynamic(): void
    {
        $router = new Router();
        $dynamicSignature = $this->createDummySignature(['users', '([^/]+)']);
        $router->add($dynamicSignature, ['UserController', 'show']);

        $staticSignature = $this->createDummySignature(['users', 'profile']);
        $router->add($staticSignature, ['UserController', 'profile']);

        $matchSignature = $this->createDummySignature(['users', 'profile']);
        $route = $router->match($matchSignature);
        $this->assertEquals(['UserController', 'profile'], $route->getHandler());
    }

    public function test_can_extract_dynamic_parameters(): void
    {
        $routeSignature = $this->createDummySignature(['users', '([^/]+)'], ['id' => '42']);
        $requestSignature = $this->createDummySignature(['users', '42']);
        $params = $routeSignature->extractParams($requestSignature);
        $this->assertEquals(['id' => '42'], $params);
    }

    public function test_can_handle_multiple_dynamic_parameters(): void
    {
        $router = new Router();
        $routeSignature = $this->createDummySignature(
            ['users', '([^/]+)', 'posts', '([^/]+)'],
            ['userId' => '10', 'postId' => '42'],
        );
        $router->add($routeSignature, ['PostController', 'show']);
        $requestSignature = $this->createDummySignature(['users', '10', 'posts', '42']);
        $route = $router->match($requestSignature);
        $params = $route->getSignature()->extractParams($requestSignature);
        $this->assertEquals(['userId' => '10', 'postId' => '42'], $params);
    }

    public function test_aggregates_hierarchical_middlewares(): void
    {
        $router = new Router();
        $groupSignature = $this->createDummySignature(['api']);

        $router->group($groupSignature, function (Router $r): void {
            $routeSignature = $this->createDummySignature(['admin']);
            $r->add($routeSignature, ['AdminController', 'index'], ['RouteMiddleware']);
        }, ['GroupMiddleware']);

        $matchSignature = $this->createDummySignature(['api', 'admin']);
        $node = $router->match($matchSignature);
        $this->assertEquals(['GroupMiddleware', 'RouteMiddleware'], $node->getMiddlewares());
    }

    public function test_route_immutability_when_adding_middleware(): void
    {
        $signature = $this->createDummySignature(['users']);
        $route = new Route($signature, ['UserController', 'index'], ['InitialMiddleware']);
        $newRoute = $route->withMiddleware('NewMiddleware');

        $this->assertEquals(['InitialMiddleware'], $route->getMiddlewares()); // Checking first instance immutability
        $this->assertEquals(['InitialMiddleware', 'NewMiddleware'], $newRoute->getMiddlewares()); // Checking cloned route's list of middlewares
        $this->assertNotSame($route, $newRoute); // Checking instances difference
    }

    public function test_route_immutability_when_setting_all_middlewares(): void
    {
        $signature = $this->createDummySignature(['users']);
        $route = new Route($signature, ['UserController', 'index'], ['InitialMiddleware']);
        $newRoute = $route->withMiddlewares(['NewMiddleware1', 'NewMiddleware2']);

        $this->assertEquals(['InitialMiddleware'], $route->getMiddlewares()); // Checking first instance immutability
        $this->assertEquals(['NewMiddleware1', 'NewMiddleware2'], $newRoute->getMiddlewares()); // Checking cloned route's list of middlewares
        $this->assertNotSame($route, $newRoute); // Checking instances difference
    }

    /**
     * Helper to mock a signature.
     *
     * @param array<string> $indexingPath
     * @param array<string, mixed> $params
     */
    private function createDummySignature(array $indexingPath, array $params = []): SignatureInterface
    {
        $signature = $this->createMock(SignatureInterface::class);
        $signature->method('getIndexingPath')
            ->willReturn($indexingPath);

        $signature->method('extractParams')
            ->willReturn($params);

        return $signature;
    }
}
