<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console\Commands;

use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use EchoFusion\Framework\Exceptions\UsageException;
use function date;
use function is_string;
use function preg_match;
use function sprintf;

final class MakeMigrationCommand extends MakeCommand
{
    private const STUB = <<<'PHP'
        <?php

        declare(strict_types=1);

        use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
        use EchoFusion\Framework\Database\Migration;

        return new class implements Migration {
            public function up(ConnectionInterface $db): void
            {
        {UP}
            }

            public function down(ConnectionInterface $db): void
            {
        {DOWN}
            }
        };

        PHP;

    public function name(): string
    {
        return 'make:migration';
    }

    public function description(): string
    {
        return 'Create a migration file in database/migrations';
    }

    public function arguments(): array
    {
        return ['name' => ['description' => 'What it does, in snake_case: create_posts_table, add_slug_to_posts', 'required' => true]];
    }

    public function options(): array
    {
        return parent::options() + ['create' => ['description' => 'Start from a CREATE TABLE for this table', 'value' => true]];
    }

    public function handle(Input $input, Output $output): int
    {
        $name = (string) $input->argument('name');
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new UsageException(sprintf('"%s" is not a valid migration name: use snake_case, like create_posts_table.', $name));
        }

        $table = $input->option('create');
        if (is_string($table) && preg_match('/^[a-z][a-z0-9_]*$/', $table) !== 1) {
            throw new UsageException(sprintf('"%s" is not a valid table name.', $table));
        }

        $up = is_string($table)
            ? sprintf("        \$db->execute('CREATE TABLE %s (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');", $table)
            : "        // \$db->execute('ALTER TABLE ...');";
        $down = is_string($table) ? sprintf("        \$db->execute('DROP TABLE %s');", $table) : '        // undo what up() did';

        $this->write(sprintf('database/migrations/%s_%s.php', date('Y_m_d_His'), $name), self::STUB, ['{UP}' => $up, '{DOWN}' => $down], $input, $output);
        $output->comment('SQL differs between databases (AUTOINCREMENT in SQLite, AUTO_INCREMENT in MySQL, SERIAL in PostgreSQL): adjust it for yours.');

        return self::SUCCESS;
    }
}
