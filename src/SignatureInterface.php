<?php

declare(strict_types=1);

namespace Virgile\Routing;

/**
 * Contract representing the Signature of a route or a request.
 */
interface SignatureInterface
{
    /**
     * The wildcard used to identify a dynamic segment within a signature's indexing path.
     */
    final public const DYN_WILDCARD = '([^/]+)';

    /**
      * Array of segments leading to a node of the router's routes tree.
      *
      * @return array<string>
      */
    public function getIndexingPath(): array;

    /**
     * Raw data used to build the indexing path.
     *
     * @return mixed
     */
    public function getRaw(): mixed;

    /**
     * Defines if the route is dynamic.
     *
     * @return bool
     */
    public function isDynamic(): bool;

    /**
     * Returns parameters used in dynamic segments by comparing a request Signature against a route Signature.
     *
     * @param SignatureInterface $signature
     * @return array<string, mixed>
     */
    public function extractParams(SignatureInterface $signature): array;
}
