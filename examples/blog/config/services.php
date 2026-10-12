<?php

declare(strict_types=1);

/*
 * Extra container definitions. A class whose constructor asks for known classes is built automatically;
 * you only need this to bind an interface or to build something by hand.
 */
return [
    'dependencies' => [
        // App\Mail\MailerInterface::class => App\Mail\SmtpMailer::class,
        // App\Clock::class => static fn () => new App\Clock('UTC'),
    ],

    // ids built once instead of on every get()
    'shared' => [
        // App\Mail\MailerInterface::class,
    ],
];
