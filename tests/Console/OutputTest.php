<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Console;

use EchoFusion\Framework\Console\Output;
use PHPUnit\Framework\TestCase;
use function array_filter;
use function array_map;
use function array_unique;
use function explode;
use function fopen;
use function implode;
use function rewind;
use function str_contains;
use function stream_get_contents;
use function trim;

final class OutputTest extends TestCase
{
    public function testPlainOutputHasNoEscapeCodes(): void
    {
        [$output, $out, $err] = $this->output(false);
        $output->info('a');
        $output->success('b');
        $output->warning('c');
        $output->comment('d');
        $output->title('e');
        $output->error('f');
        $output->newLine();

        $this->assertStringNotContainsString("\033", $this->read($out) . $this->read($err));
        $this->assertStringContainsString('f', $this->read($err));
    }

    public function testAnsiOutputIsColoured(): void
    {
        [$output, $out] = $this->output(true);
        $output->info('hello');

        $this->assertStringContainsString("\033[", $this->read($out));
        $this->assertSame("\033[32mx\033[0m", $output->color('info', 'x'));
    }

    public function testWithAnsiSwitchesWithoutChangingTheOriginal(): void
    {
        [$output] = $this->output(false);

        $this->assertSame($output, $output->withAnsi(null));
        $this->assertNotSame($output, $output->withAnsi(true));
        $this->assertSame('x', $output->color('info', 'x'));
    }

    public function testTableAlignsColumnsAndCountsCharactersNotBytes(): void
    {
        [$output, $out] = $this->output(false);
        $output->table(['Name', 'City'], [['Ann', 'Zürich'], ['Bartholomew', 'Rome']]);

        $lines = explode("\n", trim($this->read($out)));
        $this->assertStringContainsString('Name', $lines[1]);
        $this->assertStringContainsString('Zürich', implode("\n", $lines));
        $widths = array_unique(array_map('mb_strlen', array_filter($lines, static fn (string $l): bool => str_contains($l, 'Ann') || str_contains($l, 'Bartholomew'))));
        $this->assertCount(1, $widths, 'rows are the same width');
    }

    /**
     * @return array{Output, resource, resource}
     */
    private function output(bool $ansi): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');

        return [new Output($out, $err, $ansi), $out, $err];
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
