<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Console;

use EchoFusion\Framework\Application;
use EchoFusion\Framework\Console\Command;
use EchoFusion\Framework\Console\Console;
use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Console\Output;
use EchoFusion\Framework\Exceptions\FrameworkException;
use EchoFusion\Framework\Tests\TestApp;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function fopen;
use function rewind;
use function str_contains;
use function stream_get_contents;

final class ConsoleTest extends TestCase
{
    protected function tearDown(): void
    {
        TestApp::cleanUp();
    }

    public function testListShowsEveryCommand(): void
    {
        [$code, $out] = $this->exec(TestApp::create(), ['list']);

        $this->assertSame(0, $code);
        foreach (['about', 'route:list', 'config:show', 'serve', 'cache:clear', 'migrate', 'migrate:rollback', 'migrate:status', 'make:controller', 'make:migration', 'make:command'] as $name) {
            $this->assertStringContainsString($name, $out);
        }
    }

    public function testNoArgumentsListsCommands(): void
    {
        [$code, $out] = $this->exec(TestApp::create(), []);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('migrate:status', $out);
    }

    public function testHelpForACommand(): void
    {
        [$code, $out] = $this->exec(TestApp::create(), ['help', 'route:list']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('--method', $out);

        [$code, $out] = $this->exec(TestApp::create(), ['make:controller', '--help']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('name', $out);
    }

    public function testUnknownCommandSuggestsSimilarOnes(): void
    {
        [$code, $out, $err] = $this->exec(TestApp::create(), ['migrat']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('"migrat" is not defined', $err);
        $this->assertStringContainsString('migrate', $out);
    }

    public function testUsageMistakesExitWithTwo(): void
    {
        [$code, , $err] = $this->exec(TestApp::create(), ['make:controller']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('Not enough arguments', $err);

        [$code, , $err] = $this->exec(TestApp::create(), ['route:list', '--nope']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('--nope', $err);
    }

    public function testACommandThatThrowsExitsWithOneAndShowsTheTraceOnlyInDebug(): void
    {
        $boom = new class() extends Command {
            public function name(): string
            {
                return 'boom';
            }

            public function description(): string
            {
                return 'Fails';
            }

            public function handle(Input $input, Output $output): int
            {
                throw new RuntimeException('exploded');
            }
        };

        foreach ([false, true] as $debug) {
            $out = fopen('php://memory', 'w+');
            $err = fopen('php://memory', 'w+');
            $console = new Console(TestApp::create()->getContainer(), [$boom], new Output($out, $err, false), $debug);

            $this->assertSame(1, $console->run(['console', 'boom']));
            rewind($out);
            rewind($err);
            $text = (string) stream_get_contents($err) . (string) stream_get_contents($out);
            $this->assertStringContainsString('exploded', $text);
            $this->assertSame($debug, str_contains($text, 'RuntimeException'));
        }
    }

    public function testTheSameCommandTwiceIsRefused(): void
    {
        $this->expectException(FrameworkException::class);

        $app = TestApp::create();
        $app->console()->add($app->console()->commands()['about']);
    }

    public function testCommandsFromConfigAndByClassName(): void
    {
        $app = TestApp::create(['console' => ['commands' => [HelloCommand::class]]]);
        [$code, $out] = $this->exec($app, ['hello', 'Ann', '--loud']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('HELLO ANN', $out);
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
