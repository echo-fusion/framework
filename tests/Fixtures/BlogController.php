<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Fixtures;

use EchoFusion\Contracts\ConfigManager\ConfigInterface;
use EchoFusion\Contracts\TemplateManager\TemplateManagerInterface;

final class BlogController
{
    public function index(TemplateManagerInterface $templates, ConfigInterface $config): string
    {
        return $templates->render('@blog/index', ['count' => $config->get('blog.posts')]);
    }
}
