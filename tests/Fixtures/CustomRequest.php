<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

use EchoFusion\Framework\Http\Request;

/**
 * Your own request type: type-hint it in an action and the framework builds it.
 */
final class CustomRequest extends Request
{
    public function title(): string
    {
        return strtoupper($this->string('title', 'untitled'));
    }
}
