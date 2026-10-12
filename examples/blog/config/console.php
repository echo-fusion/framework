<?php

declare(strict_types=1);

use App\Console\Commands\SeedCommand;

return [
    // Your own commands: classes extending EchoFusion\Framework\Console\Command.
    // Create one with: php bin/console make:command SendReport
    'commands' => [SeedCommand::class],
];
