<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Exceptions;

/**
 * A route's action cannot be called: it is not a closure or a controller method, or an argument cannot be provided.
 */
class InvalidActionException extends FrameworkException
{
}
