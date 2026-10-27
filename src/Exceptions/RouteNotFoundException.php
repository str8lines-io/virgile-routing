<?php

declare(strict_types=1);

namespace Virgile\Routing\Exceptions;

/**
 * Exception thrown when no route matches the given request signature.
 */
class RouteNotFoundException extends RoutingException {}
