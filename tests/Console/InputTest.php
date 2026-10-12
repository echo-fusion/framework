<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Console;

use EchoFusion\Framework\Console\Input;
use EchoFusion\Framework\Exceptions\UsageException;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    private const ARGUMENTS = [
        'name' => ['description' => 'Who', 'required' => true],
        'greeting' => ['description' => 'What', 'default' => 'Hello'],
    ];

    private const OPTIONS = [
        'loud' => ['description' => 'Shout', 'short' => 'l'],
        'times' => ['description' => 'How often', 'short' => 't', 'value' => true, 'default' => '1'],
    ];

    public function testArgumentsAndDefaults(): void
    {
        $input = Input::parse(['Ann'], self::ARGUMENTS, self::OPTIONS);

        $this->assertSame('Ann', $input->argument('name'));
        $this->assertSame('Hello', $input->argument('greeting'));
        $this->assertSame(['name' => 'Ann', 'greeting' => 'Hello'], $input->arguments());
    }

    public function testOptionsInAllTheirForms(): void
    {
        $this->assertTrue(Input::parse(['Ann', '--loud'], self::ARGUMENTS, self::OPTIONS)->option('loud'));
        $this->assertTrue(Input::parse(['Ann', '-l'], self::ARGUMENTS, self::OPTIONS)->option('loud'));
        $this->assertSame('3', Input::parse(['Ann', '--times=3'], self::ARGUMENTS, self::OPTIONS)->option('times'));
        $this->assertSame('4', Input::parse(['Ann', '--times', '4'], self::ARGUMENTS, self::OPTIONS)->option('times'));
        $this->assertSame('5', Input::parse(['Ann', '-t', '5'], self::ARGUMENTS, self::OPTIONS)->option('times'));
        $this->assertSame('1', Input::parse(['Ann'], self::ARGUMENTS, self::OPTIONS)->option('times'));
        $this->assertFalse(Input::parse(['Ann'], self::ARGUMENTS, self::OPTIONS)->option('loud'));
    }

    public function testDoubleDashEndsOptions(): void
    {
        $input = Input::parse(['--', '--weird'], self::ARGUMENTS, self::OPTIONS);

        $this->assertSame('--weird', $input->argument('name'));
    }

    public function testBuiltInOptions(): void
    {
        $this->assertTrue(Input::parse(['-h'], self::ARGUMENTS, self::OPTIONS)->wantsHelp());
        $this->assertTrue(Input::parse(['Ann', '--help'], self::ARGUMENTS, self::OPTIONS)->wantsHelp());
        $this->assertTrue(Input::parse(['Ann', '--ansi'], self::ARGUMENTS, self::OPTIONS)->ansi());
        $this->assertFalse(Input::parse(['Ann', '--no-ansi'], self::ARGUMENTS, self::OPTIONS)->ansi());
        $this->assertNull(Input::parse(['Ann'], self::ARGUMENTS, self::OPTIONS)->ansi());
    }

    /**
     * @dataProvider mistakes
     *
     * @param list<string> $tokens
     */
    public function testMistakesAreUsageErrors(array $tokens, string $message): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage($message);

        Input::parse($tokens, self::ARGUMENTS, self::OPTIONS);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function mistakes(): iterable
    {
        yield 'unknown option' => [['Ann', '--nope'], 'The option "--nope" does not exist.'];
        yield 'unknown short' => [['Ann', '-z'], 'does not exist'];
        yield 'missing value' => [['Ann', '--times'], 'needs a value'];
        yield 'too many' => [['Ann', 'Hi', 'extra'], 'Too many arguments'];
        yield 'too few' => [[], 'Not enough arguments'];
    }
}
