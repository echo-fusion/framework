<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

use EchoFusion\Framework\Http\Request;
use Psr\Http\Message\ServerRequestInterface;

final class FormController
{
    /**
     * @return array<string, mixed>
     */
    public function show(Request $request): array
    {
        return ['token' => $request->csrfToken()];
    }

    /**
     * @return array<string, mixed>
     */
    public function submit(Request $request): array
    {
        return [
            'method' => $request->method(),
            'title' => $request->input('title'),
            'page' => $request->query('page'),
            'only' => $request->only('title', 'tags'),
            'wantsJson' => $request->wantsJson(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rawAndWrapped(Request $request, ServerRequestInterface $psr, CustomRequest $custom): array
    {
        return [
            'same' => $request->psr() === $psr,
            'custom' => $custom->title(),
        ];
    }
}
