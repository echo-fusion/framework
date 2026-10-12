<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

final class PostRepository
{
    /**
     * @return array{id: int, title: string}
     */
    public function find(int $id): array
    {
        return ['id' => $id, 'title' => 'Post ' . $id];
    }
}
