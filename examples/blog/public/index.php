<?php

declare(strict_types=1);

use EchoFusion\Framework\Application;

require __DIR__ . '/../vendor/autoload.php';

// Boot the application (configuration, modules, providers, listeners, plugins) and answer this request.
Application::create(\dirname(__DIR__))->run();
