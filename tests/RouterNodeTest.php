<?php

declare(strict_types=1);

namespace Virgile\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Virgile\Routing\Exceptions\InvalidMiddlewareFormatException;
use Virgile\Routing\Route;
use Virgile\Routing\RouterNode;
use Virgile\Routing\SignatureInterface;

class RouterNodeTest extends TestCase
{
    public function test_can_be_instantiated(): void
    {
        $node = new RouterNode();
        $this->assertNull($node->getRoute());
        $this->assertEmpty($node->getChildren());
    }

    public function test_can_instantiate_node_with_valid_data(): void
    {
        $middlewares = ['Middleware'];
        $children = ['users' => new RouterNode()];
        $signature = $this->createMock(SignatureInterface::class);
        $route = new Route($signature, ['Controller', 'index']);
        $node = new RouterNode($middlewares, $children, $route);

        $this->assertEquals($middlewares, $node->getMiddlewares());
        $this->assertEquals($children, $node->getChildren());
        $this->assertSame($route, $node->getRoute());
    }

    public function test_throws_exception_on_invalid_middleware_format(): void
    {
        $this->expectException(InvalidMiddlewareFormatException::class);
        /** @phpstan-ignore-next-line */
        new RouterNode([123]);
    }

    public function test_can_set_route(): void
    {
        $node = new RouterNode();
        $signature = $this->createMock(SignatureInterface::class);
        $route = new Route($signature, ['Controller', 'index']);

        $node->setRoute($route);
        $this->assertSame($route, $node->getRoute());
    }

    public function test_can_add_children(): void
    {
        $parent = new RouterNode();
        $child = new RouterNode();

        $parent->addChild('users', $child);

        $this->assertSame($child, $parent->getChild('users'));
        $this->assertNull($parent->getChild('posts'));
        $this->assertArrayHasKey('users', $parent->getChildren());
    }

    public function test_with_middlewares_returns_new_instance(): void
    {
        $node = new RouterNode(['OldMiddleware']);
        $newNode = $node->withMiddlewares(['NewMiddleware']);

        $this->assertEquals(['OldMiddleware'], $node->getMiddlewares());
        $this->assertEquals(['NewMiddleware'], $newNode->getMiddlewares());
        $this->assertNotSame($node, $newNode);
    }
}
