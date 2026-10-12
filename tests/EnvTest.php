<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests;

use EchoFusion\Framework\Application;
use PHPUnit\Framework\TestCase;
use function EchoFusion\Framework\env;

final class EnvTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['EF_TEST_A', 'EF_TEST_B', 'EF_TEST_C', 'EF_TEST_D', 'EF_TEST_E', 'EF_FROM_DOTENV', 'APP_ENV'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        TestApp::cleanUp();
    }

    public function testDefaultsAndPlainValues(): void
    {
        $this->assertNull(env('EF_TEST_MISSING'));
        $this->assertSame('fallback', env('EF_TEST_MISSING', 'fallback'));

        $_ENV['EF_TEST_A'] = 'value';
        $this->assertSame('value', env('EF_TEST_A'));
    }

    /**
     * @dataProvider specialValues
     */
    public function testSpecialStringsAreConverted(string $raw, mixed $expected): void
    {
        $_ENV['EF_TEST_B'] = $raw;

        $this->assertSame($expected, env('EF_TEST_B', 'default'));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public function specialValues(): array
    {
        return [
            'true' => ['true', true],
            'TRUE' => ['TRUE', true],
            '(true)' => ['(true)', true],
            'false' => ['false', false],
            '(false)' => ['(false)', false],
            'null' => ['null', null],
            '(null)' => ['(null)', null],
            'empty' => ['empty', ''],
            '(empty)' => ['(empty)', ''],
            'a number stays text' => ['42', '42'],
            'text that looks like a keyword' => ['truest', 'truest'],
        ];
    }

    public function testTheRealEnvironmentAndServerAreRead(): void
    {
        putenv('EF_TEST_C=from-getenv');
        $this->assertSame('from-getenv', env('EF_TEST_C'));

        $_SERVER['EF_TEST_D'] = 'from-server';
        $this->assertSame('from-server', env('EF_TEST_D'));
    }

    public function testNonStringValuesAreReturnedAsTheyAre(): void
    {
        $_ENV['EF_TEST_E'] = 12;

        $this->assertSame(12, env('EF_TEST_E'));
    }

    public function testTheDotEnvFileIsLoadedAndTheRealEnvironmentWins(): void
    {
        putenv('EF_TEST_A=from-real-environment');
        $_ENV['EF_TEST_A'] = 'from-real-environment';
        $directory = TestApp::directory(['app' => ['debug' => true]], ['.env' => "EF_FROM_DOTENV=hello\nEF_TEST_A=from-dotenv\nAPP_ENV=staging\n"]);

        $app = new Application($directory);

        $this->assertSame('hello', env('EF_FROM_DOTENV'));
        $this->assertSame('from-real-environment', env('EF_TEST_A'), 'a variable that is already set is not overwritten by .env');
        $this->assertSame('staging', $app->getEnvironment(), 'APP_ENV picks the environment');
    }

    public function testWithoutAnEnvFileAndAppEnvTheEnvironmentIsProduction(): void
    {
        unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
        putenv('APP_ENV');

        $this->assertSame('production', (new Application(TestApp::directory()))->getEnvironment());
    }
}
