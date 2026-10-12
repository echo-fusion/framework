<?php

declare(strict_types=1);

namespace App\Providers;

use EchoFusion\Contracts\ServiceManager\SettableContainerInterface;
use EchoFusion\Contracts\ServiceProvider\ServiceProviderInterface;

/**
 * Your place to register services. It is listed in config/app.php.
 *
 * Every provider is registered first and only then booted, so in boot() all services of all providers exist.
 */
final class AppServiceProvider implements ServiceProviderInterface
{
    public function register(SettableContainerInterface $container): void
    {
        // $container->set(App\Mail\MailerInterface::class, fn () => new App\Mail\SmtpMailer(), true);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function boot(SettableContainerInterface $container, array $config = []): void
    {
    }
}
