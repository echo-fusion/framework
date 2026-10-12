<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Database;

use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
use EchoFusion\Framework\Exceptions\FrameworkException;
use function array_map;
use function basename;
use function count;
use function glob;
use function is_dir;
use function is_file;
use function sort;
use function sprintf;

/**
 * Runs the files of `database/migrations` in name order and remembers which ones ran, in the table `migrations`.
 *
 * Migrations that run together share a batch number; rolling back undoes the last batch, newest first. Each migration
 * runs in a transaction, so on a database with transactional DDL (SQLite, PostgreSQL) a failing one leaves nothing half done.
 */
final class MigrationRunner
{
    private const TABLE = 'migrations';

    public function __construct(private readonly ConnectionInterface $db, private readonly string $directory)
    {
    }

    /**
     * Every migration file, with the batch it ran in or `null` when it is still pending.
     *
     * @return list<array{migration: string, batch: int|null}>
     */
    public function status(): array
    {
        $this->ensureTable();
        $ran = $this->ran();

        return array_map(
            static fn (string $name): array => ['migration' => $name, 'batch' => $ran[$name] ?? null],
            $this->files(),
        );
    }

    /**
     * @param callable(string): void|null $report called with the name of each migration that ran
     *
     * @return int how many migrations ran
     */
    public function migrate(?callable $report = null): int
    {
        $this->ensureTable();
        $ran = $this->ran();
        $pending = array_values(array_filter($this->files(), static fn (string $name): bool => !isset($ran[$name])));
        $batch = ($ran === [] ? 0 : max($ran)) + 1;

        foreach ($pending as $name) {
            $migration = $this->load($name);
            $this->db->transactional(function (ConnectionInterface $db) use ($migration, $name, $batch): void {
                $migration->up($db);
                $db->execute(sprintf('INSERT INTO %s (migration, batch) VALUES (?, ?)', self::TABLE), [$name, $batch]);
            });

            if ($report !== null) {
                $report($name);
            }
        }

        return count($pending);
    }

    /**
     * Undo the last batch, newest migration first.
     *
     * @param callable(string): void|null $report called with the name of each migration that was undone
     *
     * @return int how many migrations were undone
     */
    public function rollback(?callable $report = null): int
    {
        $this->ensureTable();
        $ran = $this->ran();
        if ($ran === []) {
            return 0;
        }

        $last = max($ran);
        $names = array_keys(array_filter($ran, static fn (int $batch): bool => $batch === $last));
        rsort($names); // newest first

        foreach ($names as $name) {
            $migration = $this->load($name);
            $this->db->transactional(function (ConnectionInterface $db) use ($migration, $name): void {
                $migration->down($db);
                $db->execute(sprintf('DELETE FROM %s WHERE migration = ?', self::TABLE), [$name]);
            });

            if ($report !== null) {
                $report($name);
            }
        }

        return count($names);
    }

    /**
     * @return list<string> migration names (file names without .php), oldest first
     */
    private function files(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $names = array_map(static fn (string $file): string => basename($file, '.php'), glob($this->directory . '/*.php') ?: []);
        sort($names);

        return $names;
    }

    /**
     * @return array<string, int> migration => batch
     */
    private function ran(): array
    {
        $ran = [];
        foreach ($this->db->fetchAll(sprintf('SELECT migration, batch FROM %s ORDER BY migration', self::TABLE)) as $row) {
            $ran[(string) $row['migration']] = (int) $row['batch'];
        }

        return $ran;
    }

    private function ensureTable(): void
    {
        $this->db->execute(sprintf('CREATE TABLE IF NOT EXISTS %s (migration VARCHAR(255) NOT NULL PRIMARY KEY, batch INTEGER NOT NULL)', self::TABLE));
    }

    private function load(string $name): Migration
    {
        $file = sprintf('%s/%s.php', $this->directory, $name);
        if (!is_file($file)) {
            throw new FrameworkException(sprintf('The migration file "%s" was run before but no longer exists.', $file));
        }

        $migration = (static fn (string $path): mixed => require $path)($file);
        if (!$migration instanceof Migration) {
            throw new FrameworkException(sprintf('The migration file "%s" must return an object implementing %s.', $file, Migration::class));
        }

        return $migration;
    }
}
