# Deployment

## Checklist

1. `composer install --no-dev --optimize-autoloader` on the server (or in your build).
2. Make **`public/`** the web root. Nothing else must be reachable from the web.
3. Set the environment in the real environment or `.env`: `APP_ENV=production`, `APP_DEBUG=false`, database settings, `SESSION_SECURE=true` (HTTPS).
4. `php bin/console migrate`.
5. `storage/` writable by the web server user.
6. `php bin/console cache:clear` after each deployment (compiled templates).

`APP_DEBUG=true` shows file paths and traces to visitors: never in production.

## Web servers

nginx:

```nginx
root /var/www/app/public;
location / { try_files $uri /index.php$is_args$args; }
location ~ \.php$ { fastcgi_pass unix:/run/php/php8.3-fpm.sock; include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; }
```

Apache: `DocumentRoot /var/www/app/public` with `FallbackResource /index.php`.

`php bin/console serve` is for development only.

## Logs

Bind a PSR-3 `LoggerInterface` in the container (for example Monolog) and server errors are logged with their trace. Without one, errors are only turned into responses.
