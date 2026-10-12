<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

final class InvokableAction
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}
