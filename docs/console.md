# The command line

```bash
php bin/console                 # list commands
php bin/console help migrate    # help of one command
```

Exit codes: `0` success, `1` the command failed, `2` wrong usage. A mistyped command gets suggestions.

| Command | Purpose |
| --- | --- |
| `about` | environment, versions, counts |
| `route:list` | routes (`--method=`, `--path=`, `--name=`) |
| `config:show [key]` | merged config; secrets are masked |
| `serve` | development server (`--host=`, `--port=`) |
| `cache:clear` | delete compiled templates and caches |
| `migrate`, `migrate:rollback`, `migrate:status` | [migrations](database.md) |
| `make:controller`, `make:migration`, `make:command` | generators |

## Your own command

```bash
php bin/console make:command SendReport
```

```php
final class SendReportCommand extends Command
{
    public function __construct(private readonly Mailer $mailer) {}      // built by the container

    public function name(): string { return 'report:send'; }
    public function description(): string { return 'Email the weekly report'; }

    public function arguments(): array { return ['to' => ['description' => 'Recipient', 'required' => true]]; }
    public function options(): array { return ['dry-run' => ['description' => 'Do not send'], 'limit' => ['description' => 'Rows', 'value' => true, 'default' => '10']]; }

    public function handle(Input $input, Output $output): int
    {
        $output->info('Sending to ' . $input->argument('to'));
        $output->table(['Name', 'Value'], [['limit', (string) $input->option('limit')]]);

        return self::SUCCESS;
    }
}
```

Register it in `config/console.php`: `return ['commands' => [SendReportCommand::class]];`. `Output` offers `writeln`, `info`, `success`, `warning`, `comment`, `error`, `title` and `table`; colours switch off when the output is not a terminal (or with `--no-ansi`).

## Cron

```
* * * * * cd /var/www/app && php bin/console report:send ops@example.com >> storage/logs/cron.log 2>&1
```
