<?php

declare(strict_types=1);

/*
 * Listeners, by event. A listener is an invokable class, [class, method] or a closure; classes are built by the
 * container. `['listener' => ..., 'priority' => -10]` runs later than the default 0.
 *
 * The framework dispatches EchoFusion\Framework\Events\{ApplicationBooted, RequestReceived, ResponsePrepared, ExceptionRaised}.
 */
return [
    'listeners' => [
        // App\Events\UserRegistered::class => [App\Listeners\SendWelcomeMail::class],
    ],
];
