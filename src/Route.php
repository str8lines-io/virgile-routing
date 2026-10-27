<?php

declare(strict_types=1);

namespace Virgile\Routing;

use Closure;
use Virgile\Routing\Exceptions\InvalidHandlerFormatException;
use Virgile\Routing\Exceptions\InvalidMiddlewareFormatException;
use Virgile\Routing\SignatureInterface;

/**
 * Represents a unique Route.
 */
final class Route
{
    /**
     * Signature expected to match with incoming requests.
     * @var SignatureInterface $signature
     */
    private readonly SignatureInterface $signature;

    /**
     * Callable associated with the Signature.
     * @var array<string>|Closure
     */
    private readonly array|Closure $handler;

    /**
     * FQCN strings or Closures to use to instantiate middlewares.
     * @var array<string|Closure> $middlewares
     */
    private readonly array $middlewares;

    /**
     * Route constructor checks $middlewares type conformity.
     *
     * @param SignatureInterface $signature
     * @param array<string>|Closure $handler
     * @param array<string|Closure> $middlewares
     * @throws InvalidMiddlewareFormatException
     */
    public function __construct(SignatureInterface $signature, array|Closure $handler, array $middlewares = [])
    {
        foreach ($middlewares as $middleware) {
            /** @phpstan-ignore-next-line */
            if (!is_string($middleware) && !$middleware instanceof Closure) {
                throw new InvalidMiddlewareFormatException('Middleware must be a FQCN string or a Closure.');
            }
        }

        if (is_array($handler)
            && (
                count($handler) !== 2
                || !isset($handler[0], $handler[1])
                /** @phpstan-ignore-next-line */
                || !is_string($handler[0])
                /** @phpstan-ignore-next-line */
                || !is_string($handler[1])
                || empty($handler[0])
                || empty($handler[1])
            )
        ) {
            throw new InvalidHandlerFormatException('Handler array must contain a class/controller and a method name.');
        }

        $this->signature = $signature;
        $this->handler = $handler;
        $this->middlewares = $middlewares;
    }

    /**
     * Adds a middleware using immutability.
     *
     * @param string|Closure $middleware
     * @return self
     */
    public function withMiddleware(string|Closure $middleware): self
    {
        $middlewares = $this->middlewares;
        $middlewares[] = $middleware;
        return new self($this->signature, $this->handler, $middlewares);
    }

    /**
     * Defines the full array of middlewares using immutability.
     *
     * @param array<string|Closure> $middlewares
     * @return self
     */
    public function withMiddlewares(array $middlewares): self
    {
        return new self($this->signature, $this->handler, $middlewares);
    }

    /**
     * Signature expected to match with incoming requests.
     *
     * @return SignatureInterface
     */
    public function getSignature(): SignatureInterface
    {
        return $this->signature;
    }

    /**
     * Callable associated with the Signature.
     *
     * @return array<string>|Closure
     */
    public function getHandler(): array|Closure
    {
        return $this->handler;
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
