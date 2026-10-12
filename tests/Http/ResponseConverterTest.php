<?php

declare(strict_types=1);

namespace EchoFusion\Framework\Tests\Http;

use EchoFusion\Framework\Http\ResponseConverter;
use EchoFusion\ResponseManager\HtmlResponse;
use EchoFusion\ResponseManager\JsonResponse;
use GuzzleHttp\Psr7\Response;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use Stringable;

final class ResponseConverterTest extends TestCase
{
    private ResponseConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ResponseConverter();
    }

    public function testAResponseIsReturnedAsItIs(): void
    {
        $response = new Response(202);

        $this->assertSame($response, $this->converter->convert($response));
    }

    public function testNullIsAnEmpty204(): void
    {
        $response = $this->converter->convert(null);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testTextIsHtml(): void
    {
        $text = $this->converter->convert('<p>hi</p>');
        $stringable = $this->converter->convert(new class() implements Stringable {
            public function __toString(): string
            {
                return 'object text';
            }
        });

        $this->assertInstanceOf(HtmlResponse::class, $text);
        $this->assertSame('<p>hi</p>', (string) $text->getBody());
        $this->assertInstanceOf(HtmlResponse::class, $stringable);
        $this->assertSame('object text', (string) $stringable->getBody());
    }

    /**
     * @dataProvider jsonValues
     */
    public function testEverythingElseIsJson(mixed $value, string $expected): void
    {
        $response = $this->converter->convert($value);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expected, (string) $response->getBody());
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public function jsonValues(): array
    {
        return [
            'array' => [['a' => 1], '{"a":1}'],
            'list' => [[1, 2], '[1,2]'],
            'int' => [7, '7'],
            'float' => [1.5, '1.5'],
            'bool' => [true, 'true'],
            'object' => [(object) ['x' => 'y'], '{"x":"y"}'],
            'serializable' => [new class() implements JsonSerializable {
                public function jsonSerialize(): array
                {
                    return ['custom' => true];
                }
            }, '{"custom":true}'],
        ];
    }
}
