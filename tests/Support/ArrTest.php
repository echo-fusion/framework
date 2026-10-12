<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Support;

use EchoFusion\Framework\Support\Arr;
use PHPUnit\Framework\TestCase;

final class ArrTest extends TestCase
{
    public function testGetWithDotKeys(): void
    {
        $data = ['user' => ['name' => 'Ann', 'tags' => ['a', 'b']], 'a.b' => 'literal', 'zero' => 0, 'null' => null];

        $this->assertSame('Ann', Arr::get($data, 'user.name'));
        $this->assertSame('b', Arr::get($data, 'user.tags.1'));
        $this->assertSame('literal', Arr::get($data, 'a.b'), 'a key that exists as written wins');
        $this->assertSame(0, Arr::get($data, 'zero', 'x'));
        $this->assertNull(Arr::get($data, 'null', 'x'), 'a null value is a value');
        $this->assertSame('x', Arr::get($data, 'missing', 'x'));
        $this->assertSame('x', Arr::get($data, 'user.age', 'x'));
        $this->assertSame('x', Arr::get($data, 'user.name.first', 'x'), 'a string has no keys');
    }

    public function testHas(): void
    {
        $data = ['a' => ['b' => null], 'c' => 0];

        $this->assertTrue(Arr::has($data, 'a.b'));
        $this->assertTrue(Arr::has($data, 'c'));
        $this->assertFalse(Arr::has($data, 'a.x'));
        $this->assertFalse(Arr::has($data, 'z'));
    }

    public function testOnlyKeepsNestingAndSkipsMissingKeys(): void
    {
        $data = ['title' => 'T', 'user' => ['name' => 'Ann', 'age' => 3], 'extra' => 1];

        $this->assertSame(['title' => 'T', 'user' => ['name' => 'Ann']], Arr::only($data, ['title', 'user.name', 'nope']));
    }

    public function testExcept(): void
    {
        $data = ['title' => 'T', 'user' => ['name' => 'Ann', 'age' => 3], 'a.b' => 1];

        $this->assertSame(['user' => ['name' => 'Ann', 'age' => 3], 'a.b' => 1], Arr::except($data, ['title']));
        $this->assertSame(['title' => 'T', 'user' => ['name' => 'Ann'], 'a.b' => 1], Arr::except($data, ['user.age']));
        $this->assertSame(['title' => 'T', 'user' => ['name' => 'Ann', 'age' => 3]], Arr::except($data, ['a.b']));
        $this->assertSame($data, Arr::except($data, ['nothing.here', 'title.deeper']));
    }

    public function testSetCreatesAndOverwritesNestedValues(): void
    {
        $data = ['scalar' => 'text'];
        Arr::set($data, 'a.b.c', 1);
        Arr::set($data, 'scalar.inner', 2);

        $this->assertSame(['scalar' => ['inner' => 2], 'a' => ['b' => ['c' => 1]]], $data);
    }
}
