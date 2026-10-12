<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Events;

use EchoFusion\Framework\Application;

/**
 * Dispatched once, when configuration, modules, providers, listeners and plugins are all in place.
 */
final class ApplicationBooted
{
    public function __construct(public readonly Application $application)
    {
    }
}
