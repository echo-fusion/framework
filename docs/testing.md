# Testing

Feature tests send a request through the whole application without a web server:

```php
final class PostsTest extends TestCase     // Tests\TestCase from the skeleton
{
    public function testHomePageWorks(): void
    {
        $response = $this->get('/');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testApiReturnsJson(): void
    {
        $response = $this->app()->handle(new ServerRequest('GET', '/api/posts', ['Accept' => 'application/json']));

        $this->assertSame(['data' => [], 'total' => 0], json_decode((string) $response->getBody(), true));
    }
}
```

`$this->app()` boots the application with `APP_ENV=testing`. `config/environments/testing.php` switches the global middleware off, so tests need no session or CSRF token. To test what the middleware does, build the request with the headers and tokens it needs.

## Database in tests

Point the tests at their own file in `phpunit.xml` and rebuild it in `setUp()` with the real migrations:

```xml
<php><env name="DB_DATABASE" value="storage/testing.sqlite" force="true"/></php>
```

```php
@unlink(dirname(__DIR__) . '/storage/testing.sqlite');
(new MigrationRunner($app->getContainer()->get(ConnectionInterface::class), dirname(__DIR__) . '/database/migrations'))->migrate();
```

The example application's [ApiTest](../examples/blog/tests/ApiTest.php) does exactly this.

## Replacing a service in a test

Bind a fake in the container before the request: `$app->getContainer()->set(Mailer::class, fn () => new FakeMailer(), true);`.

## Quality tools

`composer test` runs PHPUnit; `composer cs-check` / `cs-fix` the code style (php-cs-fixer with `echo-fusion/codestandard`).
