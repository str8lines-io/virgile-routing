<?php

declare(strict_types=1);

namespace Virgile\Routing;

use Closure;
use Virgile\Routing\Exceptions\InvalidMiddlewareFormatException;
use Virgile\Routing\Exceptions\RoutingException;

/**
 * An actual node within a reoutes tree.
 */
final class RouterNode implements RouterNodeInterface
{
    /**
     * The Route associated to this segment, or null if missing.
     * @var ?Route $route
     */
    private ?Route $route = null;

    /**
     * Children nodes indexed by their segment (static or pattern).
     * @var array<string, RouterNode>
     */
    private array $children = [];

    /**
     * FQCN strings or Closures to use to instantiate middlewares.
     * @var array<string|Closure> $middlewares
     */
    private readonly array $middlewares;

    /**
     * RouterNode constructor checks $middlewares type conformity.
     *
     * @param array<string|Closure> $middlewares
     * @param array<string, RouterNode> $children
     * @param ?Route $route
     * @throws InvalidMiddlewareFormatException
     */
    public function __construct(array $middlewares = [], array $children = [], ?Route $route = null)
    {
        foreach ($middlewares as $middleware) {
            /** @phpstan-ignore-next-line */
            if (!is_string($middleware) && !$middleware instanceof Closure) {
                throw new InvalidMiddlewareFormatException('Middleware must be a FQCN string or a Closure.');
            }
        }
        $this->middlewares = $middlewares;
        $this->children = $children;
        $this->route = $route;
    }

    /**
     * Associate a Route to current node.
     *
     * @param Route $route
     * @return void
     */
    public function setRoute(Route $route): void
    {
        $this->route = $route;
    }

    /**
     * The Route associated to this segment, or null if missing.
     *
     * @return ?Route
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * Adds a child node for a given segment.
     *
     * @param string $segment
     * @param RouterNode $node
     */
    public function addChild(string $segment, RouterNode $node): void
    {
        $this->children[$segment] = $node;
    }

    /**
     * Child node for given segment, or null if missing.
     *
     * @param string $segment
     * @return ?RouterNode
     */
    public function getChild(string $segment): ?RouterNode
    {
        return $this->children[$segment] ?? null;
    }

    /**
     * Children nodes indexed by their segment (static or pattern).
     *
     * @return array<string, RouterNodeInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Defines the full array of middlewares using immutability.
     *
     * @param array<string|Closure> $middlewares
     * @return self
     */
    public function withMiddlewares(array $middlewares): self
    {
        return new self($middlewares, $this->children, $this->route);
    }

    /**
     * FQCN strings or Closures to use to instantiate middlewares.
     *
     * @return array<string|Closure>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
