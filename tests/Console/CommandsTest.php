<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Console;

use EchoFusion\Framework\Application;
use EchoFusion\Framework\Console\Output;
use EchoFusion\Framework\Tests\Fixtures\HomeController;
use EchoFusion\Framework\Tests\TestApp;
use PDO;
use PHPUnit\Framework\TestCase;
use const PHP_BINARY;
use const PHP_VERSION;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function glob;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;

final class CommandsTest extends TestCase
{
    private const MIGRATION = <<<'PHP'
        <?php

        use EchoFusion\Contracts\DatabaseManager\ConnectionInterface;
        use EchoFusion\Framework\Database\Migration;

        return new class implements Migration {
            public function up(ConnectionInterface $db): void
            {
                $db->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)');
            }

            public function down(ConnectionInterface $db): void
            {
                $db->execute('DROP TABLE posts');
            }
        };
        PHP;

    protected function tearDown(): void
    {
        TestApp::cleanUp();
    }

    public function testAboutShowsTheEnvironment(): void
    {
        [$code, $out] = $this->exec($this->app(), ['about']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('testing', $out);
        $this->assertStringContainsString(PHP_VERSION, $out);
    }

    public function testRouteListWithFilters(): void
    {
        $app = $this->app([
            'home' => ['method' => 'GET', 'path' => '/', 'action' => [HomeController::class, 'index']],
            'save' => ['method' => 'POST', 'path' => '/save', 'action' => [HomeController::class, 'index']],
        ]);

        [, $all] = $this->exec($app, ['route:list']);
        $this->assertStringContainsString('/save', $all);
        $this->assertStringContainsString('home', $all);

        [, $posts] = $this->exec($app, ['route:list', '--method=POST']);
        $this->assertStringContainsString('/save', $posts);
        $this->assertStringNotContainsString('home', $posts);

        [, $byPath] = $this->exec($app, ['route:list', '--path=sav']);
        $this->assertStringContainsString('/save', $byPath);

        [, $byName] = $this->exec($app, ['route:list', '--name=home']);
        $this->assertStringContainsString('home', $byName);
        $this->assertStringNotContainsString('/save', $byName);
    }

    public function testConfigShowMasksSecrets(): void
    {
        $app = $this->app([], ['debug' => true, 'name' => 'Demo', 'key' => 'super-secret-value']);

        [$code, $out] = $this->exec($app, ['config:show', 'app']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Demo', $out);
        $this->assertStringNotContainsString('super-secret-value', $out);

        [$code, $out] = $this->exec($app, ['config:show', 'app.name']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Demo', $out);

        [$code, , $err] = $this->exec($app, ['config:show', 'nothing.here']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('nothing.here', $err);
    }

    public function testServeDryRunPrintsTheCommand(): void
    {
        [$code, $out] = $this->exec(new Application(TestApp::directory([], ['public/index.php' => '<?php']), 'testing'), ['serve', '--port=9123', '--host=127.0.0.1', '--dry-run']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('127.0.0.1:9123', $out);
        $this->assertStringContainsString('-t', $out);
    }

    public function testCacheClearKeepsGitignore(): void
    {
        $directory = TestApp::directory(['app' => ['debug' => true]], [
            'storage/cache/a.php' => 'x',
            'storage/cache/sub/b.php' => 'x',
            'storage/cache/.gitignore' => '*',
        ]);
        $app = new Application($directory, 'testing');

        [$code] = $this->exec($app, ['cache:clear']);

        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist($directory . '/storage/cache/a.php');
        $this->assertFileDoesNotExist($directory . '/storage/cache/sub/b.php');
        $this->assertFileExists($directory . '/storage/cache/.gitignore');
    }

    public function testMigrateStatusRollbackCycle(): void
    {
        $directory = $this->databaseApp();
        $app = new Application($directory, 'testing');

        [, $out] = $this->exec($app, ['migrate:status']);
        $this->assertStringContainsString('pending', $out);

        [$code, $out] = $this->exec($app, ['migrate']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('2024_01_01_000000_create_posts', $out);
        $this->assertSame(['posts'], $this->tables($directory));

        [, $out] = $this->exec(new Application($directory, 'testing'), ['migrate']);
        $this->assertStringContainsString('Nothing to migrate', $out);

        [, $out] = $this->exec(new Application($directory, 'testing'), ['migrate:status']);
        $this->assertStringContainsString('ran', $out);

        [, $out] = $this->exec(new Application($directory, 'testing'), ['migrate:rollback']);
        $this->assertStringContainsString('Rolled back', $out);
        $this->assertSame([], $this->tables($directory));

        [, $out] = $this->exec(new Application($directory, 'testing'), ['migrate:rollback']);
        $this->assertStringContainsString('Nothing to roll back', $out);
    }

    public function testMigrateStatusWithNoFiles(): void
    {
        $directory = TestApp::directory(['app' => ['debug' => true], 'database' => ['connections' => ['main' => ['driver' => 'sqlite', 'path' => sys_get_temp_dir() . '/ef-' . uniqid() . '.sqlite']]]]);

        [$code, $out] = $this->exec(new Application($directory, 'testing'), ['migrate:status']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('no migration files', $out);
    }

    public function testMigrationOnANamedConnection(): void
    {
        $directory = $this->databaseApp();

        [$code] = $this->exec(new Application($directory, 'testing'), ['migrate', '--database=main']);

        $this->assertSame(0, $code);
        $this->assertSame(['posts'], $this->tables($directory));
    }

    public function testMakeControllerWritesAFileAndRefusesToOverwrite(): void
    {
        $directory = TestApp::directory();
        $app = new Application($directory, 'testing');

        [$code, $out] = $this->exec($app, ['make:controller', 'Admin/Post']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('config/routes.php', $out);
        $file = $directory . '/app/Http/Controllers/Admin/PostController.php';
        $source = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\Http\Controllers\Admin;', $source);
        $this->assertStringContainsString('final class PostController', $source);
        $this->assertSame(0, $this->lint($file));

        [$code, , $err] = $this->exec(new Application($directory, 'testing'), ['make:controller', 'Admin/Post']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('already exists', $err);

        [$code] = $this->exec(new Application($directory, 'testing'), ['make:controller', 'Admin/Post', '--force']);
        $this->assertSame(0, $code);

        [$code, , $err] = $this->exec(new Application($directory, 'testing'), ['make:controller', 'bad name']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('not a valid name', $err);
    }

    public function testMakeCommandAndMakeMigrationProduceValidPhp(): void
    {
        $directory = TestApp::directory();

        [$code, $out] = $this->exec(new Application($directory, 'testing'), ['make:command', 'SendReport']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('send-report', $out);
        $this->assertSame(0, $this->lint($directory . '/app/Console/Commands/SendReportCommand.php'));

        [$code] = $this->exec(new Application($directory, 'testing'), ['make:controller', 'Home']);
        $this->assertSame(0, $code);
        $this->assertFileExists($directory . '/app/Http/Controllers/HomeController.php');

        [$code] = $this->exec(new Application($directory, 'testing'), ['make:migration', 'create_posts_table', '--create=posts']);
        $this->assertSame(0, $code);
        $files = glob($directory . '/database/migrations/*_create_posts_table.php');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE posts', (string) file_get_contents($files[0]));
        $this->assertSame(0, $this->lint($files[0]));

        [$code] = $this->exec(new Application($directory, 'testing'), ['make:migration', 'add_slug']);
        $this->assertSame(0, $code);

        [$code, , $err] = $this->exec(new Application($directory, 'testing'), ['make:migration', 'Bad Name']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('snake_case', $err);

        [$code, , $err] = $this->exec(new Application($directory, 'testing'), ['make:migration', 'ok', '--create=Bad-Table']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('table name', $err);
    }

    /**
     * @param array<string, array<string, mixed>> $routes
     * @param array<string, mixed> $app
     */
    private function app(array $routes = [], array $app = ['debug' => true]): Application
    {
        return TestApp::create(['routes' => $routes, 'app' => $app]);
    }

    private function databaseApp(): string
    {
        $directory = TestApp::directory(
            [
                'app' => ['debug' => true],
                'database' => ['connections' => ['main' => ['driver' => 'sqlite', 'path' => sys_get_temp_dir() . '/ef-' . uniqid('', true) . '.sqlite']]],
            ],
            ['database/migrations/2024_01_01_000000_create_posts.php' => self::MIGRATION],
        );

        // remember where the database file is, so tests can look inside it
        file_put_contents($directory . '/.sqlite-path', (new Application($directory, 'testing'))->getConfig()->get('database.connections.main.path'));

        return $directory;
    }

    /**
     * @return list<string>
     */
    private function tables(string $directory): array
    {
        $path = (string) file_get_contents($directory . '/.sqlite-path');
        $pdo = new PDO('sqlite:' . $path);
        $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT IN ('migrations') AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

        return $names;
    }

    private function lint(string $file): int
    {
        exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

        return $code;
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{int, string, string}
     */
    private function exec(Application $app, array $arguments): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        $code = $app->runConsole(['console', ...$arguments], new Output($out, $err, false));
        rewind($out);
        rewind($err);

        return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    }
}
