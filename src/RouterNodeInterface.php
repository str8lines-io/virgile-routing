<?php

declare(strict_types=1);

namespace Virgile\Routing;

use Closure;

/**
 * Contract representing a node within the routes tree.
 * Meant to provide interface segregation.
 */
interface RouterNodeInterface
{
    /**
     * Returns the Route associated to current RouterNodeInterface, or null if missing.
     *
     * @return ?Route
     */
    public function getRoute(): ?Route;

    /**
     * Returns current RouterNodeInterface children indexed by their segments.
     *
     * @return array<string, RouterNodeInterface>
     */
    public function getChildren(): array;

    /**
     * Defines the full array of middlewares using immutability.
     *
     * @param array<string|Closure> $middlewares
     * @return self
     */
    public function withMiddlewares(array $middlewares): self;

    /**
     * Returns associated middlewares as FQCN strings or Closures.
     *
     * @return array<string|Closure>
     */
    public function getMiddlewares(): array;
}
