<?php

declare(strict_types=1);

namespace Virgile\Routing\Exceptions;

/**
 * Exception thrown when no router node matches the given request signature.
 */
class NodeNotFoundException extends RoutingException {}
