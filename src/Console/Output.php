<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Console;

use const PHP_EOL;
use const STDERR;
use const STDOUT;
use function array_map;
use function fwrite;
use function getenv;
use function implode;
use function is_resource;
use function max;
use function mb_strlen;
use function sprintf;
use function str_repeat;
use function stream_isatty;

/**
 * Where a command writes. Colors are used only on a terminal (and when `NO_COLOR` is not set), unless forced.
 */
final class Output
{
    private const COLORS = ['info' => '32', 'warning' => '33', 'error' => '31', 'comment' => '90', 'title' => '1;33'];

    private readonly bool $ansi;

    /**
     * @param resource $stream where normal output goes
     * @param resource $errorStream where errors go
     */
    public function __construct(private $stream = STDOUT, private $errorStream = STDERR, ?bool $ansi = null)
    {
        $this->ansi = $ansi ?? (is_resource($this->stream) && stream_isatty($this->stream) && getenv('NO_COLOR') === false);
    }

    public function withAnsi(?bool $ansi): self
    {
        return $ansi === null ? $this : new self($this->stream, $this->errorStream, $ansi);
    }

    public function write(string $text): void
    {
        fwrite($this->stream, $text);
    }

    public function writeln(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    public function newLine(int $count = 1): void
    {
        $this->write(str_repeat(PHP_EOL, $count));
    }

    public function info(string $text): void
    {
        $this->writeln($this->color('info', $text));
    }

    public function success(string $text): void
    {
        $this->writeln($this->color('info', $text));
    }

    public function warning(string $text): void
    {
        $this->writeln($this->color('warning', $text));
    }

    public function comment(string $text): void
    {
        $this->writeln($this->color('comment', $text));
    }

    public function title(string $text): void
    {
        $this->writeln($this->color('title', $text));
    }

    public function error(string $text): void
    {
        fwrite($this->errorStream, $this->color('error', $text) . PHP_EOL);
    }

    public function color(string $style, string $text): string
    {
        return $this->ansi && isset(self::COLORS[$style]) ? sprintf("\033[%sm%s\033[0m", self::COLORS[$style], $text) : $text;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = array_map(static fn (string $header): int => mb_strlen($header), $headers);
        foreach ($rows as $row) {
            foreach ($row as $column => $cell) {
                $widths[$column] = max($widths[$column] ?? 0, mb_strlen($cell));
            }
        }

        $line = static fn (array $cells): string => '| ' . implode(' | ', array_map(static fn (int $i): string => self::pad($cells[$i] ?? '', $widths[$i]), array_keys($widths))) . ' |';
        $rule = '+-' . implode('-+-', array_map(static fn (int $width): string => str_repeat('-', $width), $widths)) . '-+';

        $this->writeln($rule);
        $this->writeln($this->color('title', $line($headers)));
        $this->writeln($rule);
        foreach ($rows as $row) {
            $this->writeln($line($row));
        }
        $this->writeln($rule);
    }

    private static function pad(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(0, $width - mb_strlen($text)));
    }
}
