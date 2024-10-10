<?php

declare(strict_types=1);

use EchoFusion\Framework\Utils\Path;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    public function testRoot()
    {
        $expected = realpath(__DIR__ . '/../../../..');
        $this->assertEquals($expected, Path::root());
    }

    public function testApp()
    {
        $expected = Path::root() . '/app';
        $this->assertEquals($expected, Path::app());
    }

    public function testPublic()
    {
        $expected = Path::root() . '/public';
        $this->assertEquals($expected, Path::public());
    }

    public function testConfig()
    {
        $expected = Path::root() . '/config';
        $this->assertEquals($expected, Path::config());
    }

    public function testTemplates()
    {
        $expected = Path::root() . '/templates';
        $this->assertEquals($expected, Path::templates());
    }

    public function testStorage()
    {
        $expected = Path::root() . '/storage';
        $this->assertEquals($expected, Path::storage());
    }

    public function testMigrations()
    {
        $expected = Path::root() . '/migrations';
        $this->assertEquals($expected, Path::migrations());
    }

    public function testModules()
    {
        $expected = Path::root() . '/modules';
        $this->assertEquals($expected, Path::modules());
    }
}
