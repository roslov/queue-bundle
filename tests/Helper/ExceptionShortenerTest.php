<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Helper;

use Iterator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Helper\ExceptionShortener;

/**
 * Tests processing of the exception trace and message by the shortener.
 */
final class ExceptionShortenerTest extends TestCase
{
    /**
     * Tests processing of the exception trace by the shortener.
     *
     * @param string $traceString Original trace string
     * @param string $expectedResult Expected result
     */
    #[DataProvider('traceProvider')]
    public function testCorrectTraceShortening(string $traceString, string $expectedResult): void
    {
        $traceShortener = new ExceptionShortener(0, 64);
        $shortenedTrace = $traceShortener->processTrace($traceString);
        $this->assertSame($expectedResult, $shortenedTrace);
    }

    /**
     * Tests processing of the exception message by the shortener.
     *
     * @param string $messageString Original message string
     * @param string $expectedResult Expected result
     */
    #[DataProvider('messageProvider')]
    public function testCorrectMessageShortening(string $messageString, string $expectedResult): void
    {
        $traceShortener = new ExceptionShortener(40, 0, 2);
        $shortenedMessage = $traceShortener->processMessage($messageString);
        $this->assertSame($expectedResult, $shortenedMessage);
    }

    /**
     * Tests that processTrace throws LogicException when cutLengthStep is less than 1.
     */
    public function testProcessTraceThrowsWhenCutLengthStepIsLessThanOne(): void
    {
        $shortener = new ExceptionShortener(0, 10, 0);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cut length cannot be less than 1');

        $shortener->processTrace('Long trace that exceeds maxTraceSize');
    }

    /**
     * Returns data for the exception trace processing test.
     *
     * @return Iterator<(int | string), array<string>> Initial and corrected exception trace strings
     */
    public static function traceProvider(): Iterator
    {
        yield 'trace is too large' => [
            "#0 /app/vendor/symfony/\n\n#1 symfony/src/Symfony/\n#2 Bundle/FrameworkBundle/\n#3 trace text/",
            "#0 /app/vendor/symfony/\n\n...",
        ];

        yield 'trace is less than max size' => [
            "#0 /app/vendor/symfony/\n\n#1 trace text/",
            "#0 /app/vendor/symfony/\n\n#1 trace text/",
        ];

        yield 'trace is equal to max size' => [
            "#0 /app/vendor/symfony/\n\n#1 trace text/\n#1trace/ds",
            "#0 /app/vendor/symfony/\n\n#1 trace text/\n#1trace/ds",
        ];

        yield 'trace’s first line is too large' => [
            "#0 /app/vendor/symfony/#1 symfony/src/Symfony/#2 Bundle/FrameworkBundle/#3 trace text/\nmore text",
            '...',
        ];

        yield 'trace is empty' => [
            '',
            '',
        ];

        yield 'trace contains a lot of symbols with much longer serialized representation' => [
            "😁\n😁\n😁\n😁\n😁\n😁\n😁\n😁\n😁",
            "😁\n...",
        ];
    }

    /**
     * Returns data for an exception message processing test.
     *
     * @return Iterator<(int | string), array<string>> Initial and corrected exception message strings
     */
    public static function messageProvider(): Iterator
    {
        yield 'encoded message is too large' => [
            // JSON encoded (83 chars):
            // "The very long one-byte message that exceeds the maximum allowed size for messages"
            'The very long one-byte message that exceeds the maximum allowed size for messages',
            'The very long one-byte message tha...',
        ];

        yield 'encoded message is less than max size' => [
            // JSON encoded (19 chars): "The small message"
            'The small message',
            'The small message',
        ];

        yield 'encoded message is equal to max size' => [
            // JSON encoded (40 chars): "The one-byte message that fits exactly"
            'The one-byte message that fits exactly',
            'The one-byte message that fits exactly',
        ];

        yield 'message is empty' => [
            '',
            '',
        ];

        yield '2-byte symbols are trimmed correctly' => [
            // JSON encoded (50 chars):
            // "\u263a\u263a\u263a\u263a\u263a\u263a\u263a\u263a"
            '☺☺☺☺☺☺☺☺',
            '☺☺...',
        ];

        yield '4-byte symbols are trimmed correctly' => [
            // JSON encoded (98 chars):
            // "\ud83d\ude01\ud83d\ude01\ud83d\ude01\ud83d\ude01\ud83d\ude01\ud83d\ude01\ud83d\ude01\ud83d\ude01"
            '😁😁😁😁😁😁😁😁',
            '😁...',
        ];

        yield 'multi-byte symbols not trimmed' => [
            '😁',
            '😁',
        ];
    }
}
