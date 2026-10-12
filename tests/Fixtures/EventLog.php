<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

use EchoFusion\Framework\Events\ExceptionRaised;
use EchoFusion\Framework\Events\RequestReceived;
use EchoFusion\Framework\Events\ResponsePrepared;
use GuzzleHttp\Psr7\Response;

/**
 * Listeners used by the tests. They write to a static log, since the container builds them.
 */
final class EventLog
{
    /**
     * @var list<string>
     */
    public static array $entries = [];

    public static function reset(): void
    {
        self::$entries = [];
    }

    public function __invoke(object $event): void
    {
        self::$entries[] = 'invoked:' . $event::class;
    }

    public function onRequest(RequestReceived $event): void
    {
        self::$entries[] = 'method:request';
        $event->request = $event->request->withAttribute('seen-by-listener', true);
    }

    public function onResponse(ResponsePrepared $event): void
    {
        self::$entries[] = 'method:response';
        $event->response = $event->response->withHeader('X-Prepared', (string) ($event->request->getAttribute('seen-by-listener') ? 'yes' : 'no'));
    }

    public function onException(ExceptionRaised $event): void
    {
        self::$entries[] = 'method:exception:' . $event->exception->getMessage();
        $event->response = new Response(418, [], 'teapot: ' . $event->exception->getMessage());
    }

    public function high(object $event): void
    {
        self::$entries[] = 'high';
    }

    public function low(object $event): void
    {
        self::$entries[] = 'low';
    }
}
