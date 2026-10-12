<?php

declare(strict_types=1);

// Merged on top of config/*.php when APP_ENV=testing (the tests use it).
return [
    'app' => ['debug' => true],

    // PHPUnit has already produced output, so PHP could not start a session
    'middleware' => ['core' => []],
];
