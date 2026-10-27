<?php

declare(strict_types=1);

namespace Virgile\Routing\Exceptions;

/**
 * Exception thrown when a handler is not an array of two non empty strings.
 */
class InvalidHandlerFormatException extends RoutingException {}
