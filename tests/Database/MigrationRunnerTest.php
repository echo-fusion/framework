<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Database;

use EchoFusion\DatabaseManager\Connections\PdoConnection;
use EchoFusion\Framework\Database\MigrationRunner;
use EchoFusion\Framework\Exceptions\FrameworkException;
use EchoFusion\Framework\Tests\TestApp;
use PHPUnit\Framework\TestCase;
use Throwable;
use function count;
use function is_array;
use function sprintf;
use function unlink;

final class MigrationRunnerTest extends TestCase
{
    private const MIGRATION = <<<'PHP'
        <?php

        use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
        use EchoFusion\Framework\Database\Migration;

        return new class implements Migration {
            public function up(ConnectionInterface $db): void
            {
                $db->execute('CREATE TABLE %1$s (id INTEGER PRIMARY KEY)');%2$s
            }

            public function down(ConnectionInterface $db): void
            {
                $db->execute('DROP TABLE %1$s');
            }
        };
        PHP;

    protected function tearDown(): void
    {
        TestApp::cleanUp();
    }

    public function testBatchesAreRolledBackTogetherNewestFirst(): void
    {
        $db = new PdoConnection(['driver' => 'sqlite', 'path' => ':memory:']);
        $directory = $this->migrations(['2024_01_01_000000_a' => 'a', '2024_01_02_000000_b' => 'b']);
        $runner = new MigrationRunner($db, $directory);

        $ran = [];
        $this->assertSame(2, $runner->migrate(static function (string $name) use (&$ran): void {
            $ran[] = $name;
        }));
        $this->assertSame(['2024_01_01_000000_a', '2024_01_02_000000_b'], $ran);
        $this->assertSame([1, 1], array_column($runner->status(), 'batch'));

        $this->migrations(['2024_02_01_000000_c' => 'c'], $directory);
        $this->assertSame(1, $runner->migrate());
        $this->assertSame([1, 1, 2], array_column($runner->status(), 'batch'));

        $this->assertSame(1, $runner->rollback(), 'only the last batch');
        $this->assertSame([1, 1, null], array_column($runner->status(), 'batch'));
        $this->assertSame(2, $runner->rollback());
        $this->assertSame(0, $runner->rollback());
        $this->assertSame(0, $runner->migrate() - 3, 'everything can run again');
    }

    public function testAFailingMigrationLeavesNoTraceAndStopsTheRun(): void
    {
        $db = new PdoConnection(['driver' => 'sqlite', 'path' => ':memory:']);
        $directory = $this->migrations([
            '2024_01_01_000000_a' => 'a',
            '2024_01_02_000000_b' => ['b', "\n        \$db->execute('THIS IS NOT SQL');"],
        ]);
        $runner = new MigrationRunner($db, $directory);

        try {
            $runner->migrate();
            $this->fail('the broken migration should throw');
        } catch (Throwable) {
        }

        $this->assertSame(['ran' => 1], ['ran' => count(array_filter($runner->status(), static fn (array $row): bool => $row['batch'] !== null))]);
        $this->assertSame([], $db->fetchAll("SELECT name FROM sqlite_master WHERE name = 'b'"), 'the failed migration was rolled back');
    }

    public function testAMissingDirectoryMeansNothingToDo(): void
    {
        $runner = new MigrationRunner(new PdoConnection(['driver' => 'sqlite', 'path' => ':memory:']), '/does/not/exist');

        $this->assertSame([], $runner->status());
        $this->assertSame(0, $runner->migrate());
    }

    public function testFilesThatAreNotMigrationsAreRejected(): void
    {
        $directory = TestApp::directory([], ['m/2024_01_01_000000_bad.php' => '<?php return 42;']) . '/m';
        $runner = new MigrationRunner(new PdoConnection(['driver' => 'sqlite', 'path' => ':memory:']), $directory);

        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage('must return an object implementing');

        $runner->migrate();
    }

    public function testARanMigrationWhoseFileWasDeletedIsReported(): void
    {
        $db = new PdoConnection(['driver' => 'sqlite', 'path' => ':memory:']);
        $directory = $this->migrations(['2024_01_01_000000_a' => 'a']);
        $runner = new MigrationRunner($db, $directory);
        $runner->migrate();
        unlink($directory . '/2024_01_01_000000_a.php');

        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage('no longer exists');

        $runner->rollback();
    }

    /**
     * @param array<string, string|array{string, string}> $files name => table, or [table, extra code in up()]
     */
    private function migrations(array $files, ?string $directory = null): string
    {
        $contents = [];
        foreach ($files as $name => $spec) {
            [$table, $extra] = is_array($spec) ? $spec : [$spec, ''];
            $contents['m/' . $name . '.php'] = sprintf(self::MIGRATION, $table, $extra);
        }

        if ($directory !== null) {
            foreach ($contents as $relative => $code) {
                file_put_contents($directory . '/' . substr($relative, 2), $code);
            }

            return $directory;
        }

        return TestApp::directory([], $contents) . '/m';
    }
}
