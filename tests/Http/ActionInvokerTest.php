<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Http;

use Countable;
use EchoFusion\Framework\Exceptions\HttpException;
use EchoFusion\Framework\Exceptions\InvalidActionException;
use EchoFusion\Framework\Http\ActionInvoker;
use EchoFusion\Framework\Tests\Fixtures\HomeController;
use EchoFusion\Framework\Tests\Fixtures\InvokableAction;
use EchoFusion\Framework\Tests\Fixtures\PostRepository;
use EchoFusion\ServiceManager\ServiceManager;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class ActionInvokerTest extends TestCase
{
    private ActionInvoker $invoker;

    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        $this->invoker = new ActionInvoker(new ServiceManager());
        $this->request = new ServerRequest('GET', '/');
    }

    public function testClosuresGetPlaceholdersTheRequestAndServices(): void
    {
        $result = $this->invoker->invoke(
            static fn (string $slug, ServerRequestInterface $request, PostRepository $posts): array => [$slug, $request->getMethod(), $posts::class],
            $this->request,
            ['slug' => 'hello'],
        );

        $this->assertSame(['hello', 'GET', PostRepository::class], $result);
    }

    public function testInvokableClassNames(): void
    {
        $this->assertSame('invoked', $this->invoker->invoke(InvokableAction::class, $this->request, []));
    }

    /**
     * @dataProvider conversions
     */
    public function testPlaceholdersAreConvertedToTheParameterType(string $type, string $raw, mixed $expected): void
    {
        $action = match ($type) {
            'int' => static fn (int $v) => $v,
            'float' => static fn (float $v) => $v,
            'bool' => static fn (bool $v) => $v,
            default => static fn (string $v) => $v,
        };

        $this->assertSame($expected, $this->invoker->invoke($action, $this->request, ['v' => $raw]));
    }

    /**
     * @return array<string, array{string, string, mixed}>
     */
    public function conversions(): array
    {
        return [
            'int' => ['int', '42', 42],
            'negative int' => ['int', '-7', -7],
            'float' => ['float', '1.5', 1.5],
            'float from int text' => ['float', '3', 3.0],
            'bool true' => ['bool', 'true', true],
            'bool 1' => ['bool', '1', true],
            'bool false' => ['bool', 'false', false],
            'string' => ['string', 'abc', 'abc'],
        ];
    }

    /**
     * @dataProvider unconvertible
     */
    public function testAValueThatCannotBeConvertedIsA404(string $type, string $raw): void
    {
        $action = match ($type) {
            'int' => static fn (int $v) => $v,
            'float' => static fn (float $v) => $v,
            default => static fn (bool $v) => $v,
        };

        try {
            $this->invoker->invoke($action, $this->request, ['v' => $raw]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function unconvertible(): array
    {
        return [
            'int from text' => ['int', 'abc'],
            'int from float' => ['int', '1.5'],
            'int from empty' => ['int', ''],
            'int from a lone minus' => ['int', '-'],
            'float from text' => ['float', 'x'],
            'bool from text' => ['bool', 'maybe'],
        ];
    }

    public function testDefaultsAndNullableParameters(): void
    {
        $result = $this->invoker->invoke(
            static fn (int $page = 1, ?string $search = null, ?PostRepository $missing = null): array => [$page, $search, $missing instanceof PostRepository],
            $this->request,
            [],
        );

        $this->assertSame([1, null, true], $result, 'a class the container can build is injected even when it is optional');
    }

    public function testAControllerIsBuiltByTheContainer(): void
    {
        $result = $this->invoker->invoke([HomeController::class, 'index'], $this->request, []);

        $this->assertSame('home', $result);
    }

    /**
     * @dataProvider invalidActions
     *
     * @param array<array-key, mixed>|string $action
     */
    public function testInvalidActions(array|string $action, string $message): void
    {
        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage($message);

        $this->invoker->invoke($action, $this->request, []);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public function invalidActions(): array
    {
        return [
            'missing class' => [['No\\Such\\Controller', 'index'], 'does not exist'],
            'missing method' => [[HomeController::class, 'nope'], 'does not exist'],
            'private method' => [[HomeController::class, 'hidden'], 'must be public'],
            'not an action shape' => [['one'], 'must be a closure'],
            'a class without __invoke' => [PostRepository::class, 'does not exist'],
        ];
    }

    public function testAnArgumentThatCannotBeProvidedIsNamed(): void
    {
        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage('Cannot provide the argument $unknown of EchoFusion\Framework\Tests\Fixtures\HomeController::missingDependency()');

        $this->invoker->invoke([HomeController::class, 'missingDependency'], $this->request, []);
    }

    public function testAClassArgumentTheContainerCannotProvideSaysSo(): void
    {
        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage('the container has no "Countable"');

        $this->invoker->invoke(static fn (Countable $c) => $c, $this->request, []);
    }

    public function testAClosureArgumentWithoutAnythingIsDescribed(): void
    {
        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage('the route closure');

        $this->invoker->invoke(static fn (int $x) => $x, $this->request, []);
    }
}
