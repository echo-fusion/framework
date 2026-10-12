<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests;

use EchoFusion\Framework\Application;
use EchoFusion\Framework\Exceptions\ConfigurationException;
use EchoFusion\Framework\Tests\Fixtures\EventLog;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MisconfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        TestApp::cleanUp();
    }

    public function testAMissingConfigDirectory(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('config directory');

        (new Application(sys_get_temp_dir() . '/ef-definitely-missing', 'testing'))->boot();
    }

    public function testAConfigFileThatReturnsNothingUseful(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must return an array');

        TestApp::create(['broken' => '<?php return "text";'])->boot();
    }

    public function testAnEnvironmentFileThatReturnsNothingUseful(): void
    {
        $directory = TestApp::directory(['app' => ['debug' => true]], ['config/environments/testing.php' => '<?php return 5;']);

        $this->expectException(ConfigurationException::class);
        (new Application($directory, 'testing'))->boot();
    }

    /**
     * @dataProvider invalidSettings
     *
     * @param array<string, array<array-key, mixed>> $config
     */
    public function testInvalidSettings(array $config, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        TestApp::create($config)->boot();
    }

    /**
     * @return array<string, array{array<string, array<array-key, mixed>>, string}>
     */
    public function invalidSettings(): array
    {
        return [
            'module is not a class' => [['app' => ['modules' => ['No\\Such\\Module']]], 'app.modules lists "No\\Such\\Module"'],
            'module has the wrong type' => [['app' => ['modules' => [stdClass::class]]], 'which is not a class implementing'],
            'module is not a string' => [['app' => ['modules' => [5]]], 'app.modules lists "int"'],
            'provider is not a class' => [['app' => ['providers' => ['No\\Such\\Provider']]], 'app.providers lists "No\\Such\\Provider"'],
            'provider has the wrong type' => [['app' => ['providers' => [stdClass::class => 'x']]], 'which is not a class implementing'],
            'provider entry is not a string' => [['app' => ['providers' => [5 => 5]]], 'app.providers lists "int"'],
            'service definition' => [['services' => ['dependencies' => ['broken' => 5]]], 'services.dependencies["broken"]'],
            'listener is not usable' => [['events' => ['listeners' => ['e' => [5]]]], 'An event listener must be'],
        ];
    }

    public function testAProviderListedByNameAloneGetsAnEmptySection(): void
    {
        EventLog::reset();

        TestApp::create(['app' => ['debug' => true, 'providers' => [Fixtures\BlogProvider::class]]])->boot();

        $this->assertSame(['blog:register', 'blog:boot:empty'], EventLog::$entries);
    }
}
