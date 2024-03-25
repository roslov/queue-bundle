<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Helper;

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
     *
     * @dataProvider traceProvider
     */
    public function testCorrectTraceShortening(string $traceString, string $expectedResult): void
    {
        $traceShortener = new ExceptionShortener(0, 64);
        $shortenedTrace = $traceShortener->processTrace($traceString);
        $this->assertEquals($expectedResult, $shortenedTrace);
    }

    /**
     * Tests processing of the exception message by the shortener.
     *
     * @param string $messageString Original message string
     * @param string $expectedResult Expected result
     *
     * @dataProvider messageProvider
     */
    public function testCorrectMessageShortening(string $messageString, string $expectedResult): void
    {
        $traceShortener = new ExceptionShortener(20, 0, 3);
        $shortenedMessage = $traceShortener->processMessage($messageString);
        $this->assertEquals($expectedResult, $shortenedMessage);
    }

    /**
     * Returns data for exception trace processing test.
     *
     * @return string[][] Initial and corrected exception trace strings
     */
    public function traceProvider(): array
    {
        return [
            'trace is too large' => [
                "#0 /app/vendor/symfony/\n\n#1 symfony/src/Symfony/\n#2 Bundle/FrameworkBundle/\n#3 trace text/",
                "#0 /app/vendor/symfony/\n\n...",
            ],
            'trace is less than max size' => [
                "#0 /app/vendor/symfony/\n\n#1 trace text/",
                "#0 /app/vendor/symfony/\n\n#1 trace text/",
            ],
            'trace is equal to max size' => [
                "#0 /app/vendor/symfony/\n\n#1 trace text/\n#1trace/ds",
                "#0 /app/vendor/symfony/\n\n#1 trace text/\n#1trace/ds",
            ],
            'trace’s first line is too large' => [
                "#0 /app/vendor/symfony/#1 symfony/src/Symfony/#2 Bundle/FrameworkBundle/#3 trace text/\nmore text",
                '...',
            ],
            'trace is empty' => [
                '',
                '',
            ],
            'trace contains a lot of symbols with much longer serialized representation' => [
                "😁\n😁\n😁\n😁\n😁\n😁\n😁\n😁\n😁",
                "😁\n...",
            ],
        ];
    }

    /**
     * Returns data for exception message processing test.
     *
     * @return string[][] Initial and corrected exception message strings
     */
    public function messageProvider(): array
    {
        return [
            'message is too large' => [
                'cURL error 28: Operation timed outcURL error 28: Operation timed out',
                'cURL error...',
            ],
            'message is less than max size' => [
                'cURL',
                'cURL',
            ],
            'message is equal to max size' => [
                'cURL e',
                'cURL e',
            ],
            'message is empty' => [
                '',
                '',
            ],
            'multi-byte symbols are trimmed correctly' => [
                '😁😁😁😁😁😁😁😁',
                '😁...',
            ],
            'multi-byte symbols not trimmed' => [
                '😁',
                '😁',
            ],
        ];
    }
}
