<?php

declare(strict_types=1);

namespace Virgile\Routing;

use Closure;
use Virgile\Routing\Exceptions\NodeNotFoundException;
use Virgile\Routing\Exceptions\RouteNotFoundException;
use Virgile\Routing\SignatureInterface;

/**
 * Service which provides an API to build, store and retrieve a Route.
 */
final class Router
{
    /**
     * Root node containing every stored routes.
     * @var RouterNode
     */
    private RouterNode $routes;

    /**
     * Controller to use for a group of routes.
     * @var string|null
     */
    private ?string $controller = null;

    /**
     * A pointer to a node used when browsing the routes.
     * @var RouterNode
     */
    private RouterNode $currentNode;

    /**
     * Router constructor initialises the root node.
     */
    public function __construct()
    {
        $this->routes = new RouterNode();
        $this->currentNode = $this->routes;
    }

    /**
     * Defines a group of routes with common segments and middlewares.
     *
     * @param SignatureInterface $signature
     * @param callable(Router):void $callback
     * @param array<string|Closure> $middlewares
     * @return self
     */
    public function group(SignatureInterface $signature, callable $callback, array $middlewares = []): self
    {
        $indexingPath = $signature->getIndexingPath();
        $previous = $this->currentNode; // Save previous group context
        $current = $this->currentNode;

        // Find or create the nodes relative to Signature indexing path
        $parent = null;
        $lastSegment = null;
        foreach ($indexingPath as $segment) {
            $next = $current->getChild($segment);
            if ($next === null) {
                $next = new RouterNode();
                $current->addChild($segment, $next);
            }

            $parent = $current;
            $lastSegment = $segment;
            $current = $next;
        }

        /** @phpstan-ignore-next-line */
        if (!empty($middlewares) && $parent !== null && $lastSegment !== null) {
            $middlewares = $this->makeMiddlewaresUnique($middlewares); // Handle both strings and closures unicity
            $current = $current->withMiddlewares($middlewares);
            $parent->addChild($lastSegment, $current);
        }

        $this->currentNode = $current;
        $callback($this); // Execute scoped closure
        $this->currentNode = $previous; // Restore previous group context
        return $this;
    }

    /**
     * Defines a controller scope for a group of routes.
     * If the handler given in add() method is a string,
     * it will be considered as a method name belonging to the controller.
     *
     * @param string $controller
     * @param callable(Router):void $callable
     * @return self
     */
    public function controller(string $controller, callable $callable): self
    {
        $this->controller = $controller; // Save current controller
        $callable($this); // Execute scoped closure
        $this->controller = null; // Reset current controller
        return $this;
    }

    /**
     * Adds a Route to routing tree.
     *
     * @param SignatureInterface $signature
     * @param string|array<string>|Closure $handler
     * @param array<string|Closure> $middlewares
     * @return Route
     */
    public function add(SignatureInterface $signature, string|array|Closure $handler, array $middlewares = []): Route
    {
        // Casts and normalize handler when a string is given
        if (is_string($handler)) {
            $handler = ($this->controller === null) ? [$handler, 'index'] : [$this->controller, $handler];
        }

        $middlewares = $this->makeMiddlewaresUnique($middlewares); // Handle both strings and closures unicity
        $route = new Route($signature, $handler, $middlewares);
        $indexingPath = $signature->getIndexingPath();
        $current = $this->currentNode; // Define starting node
        foreach ($indexingPath as $segment) {
            $next = $current->getChild($segment);
            if ($next === null) {
                $next = new RouterNode();
                $current->addChild($segment, $next);
            }

            $current = $next;
        } // Identify or create nodes
        $current->setRoute($route); // Define last node's Route
        return $route;
    }

    /**
     * Look for RouterNode corresponding to a Signature and aggregates hierarchical middlewares.
     * The node can have no Route associated.
     *
     * @param SignatureInterface $signature
     * @return RouterNodeInterface
     * @throws RouteNotFoundException
     */
    public function findNode(SignatureInterface $signature): RouterNodeInterface
    {
        $indexingPath = $signature->getIndexingPath();
        $current = $this->routes; // Define starting node
        $middlewares = $current->getMiddlewares(); // Gather global middlewares
        foreach ($indexingPath as $segment) {
            $next = $current->getChild($segment); // Try to access next static child node
            if ($next === null) {
                $next = $current->getChild($signature::DYN_WILDCARD);
            } // Try to access dynamic child node if static not found

            if ($next === null) {
                throw new NodeNotFoundException("Router node Not Found");
            } // Handle missing node

            $middlewares = $this->makeMiddlewaresUnique(array_merge(
                $middlewares,
                $next->getMiddlewares(),
            )); // Gather node's middlewares ensuring both FQCN and Closures unicity
            $current = $next;
        }

        return $current->withMiddlewares($middlewares);
    }

    /**
     * Look for a Route corresponding to a Signature and aggregates hierarchical middlewares.
     *
     * @param SignatureInterface $signature
     * @return Route
     * @throws RouteNotFoundException
     */
    public function match(SignatureInterface $signature): Route
    {
        $node = $this->findNode($signature);
        $route = $node->getRoute(); // Retrieve node's Route
        if ($route === null) {
            throw new RouteNotFoundException("Route Not Found");
        } // Ensure immutability and return empty node with gathered middlewares

        $middlewares = $this->makeMiddlewaresUnique(array_merge(
            $node->getMiddlewares(),
            $route->getMiddlewares(),
        )); // Gather route's middlewares ensuring both FQCN and Closures unicity
        $route = $route->withMiddlewares($middlewares); // Ensure route's immutability
        return $route;
    }

    /**
     * Remove duplicated middlewares (Strings and Closures) and preserves order.
     *
     * @param array<string|Closure> $middlewares
     * @return array<string|Closure>
     */
    private function makeMiddlewaresUnique(array $middlewares): array
    {
        $unique = [];
        foreach ($middlewares as $middleware) {
            if (!in_array($middleware, $unique, true)) {
                $unique[] = $middleware;
            }
        }
        return $unique;
    }
}
