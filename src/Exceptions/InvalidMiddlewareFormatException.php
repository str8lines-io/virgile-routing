<?php

declare(strict_types=1);

namespace Virgile\Routing\Exceptions;

/**
 * Exception thrown when a middleware is neither a valid FQCN string nor a Closure.
 */
class InvalidMiddlewareFormatException extends RoutingException {}
