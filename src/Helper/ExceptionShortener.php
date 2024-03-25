<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Helper;

use LogicException;

/**
 * Checks whether the exception trace and message strings are bigger than their limits and accurately shortens it.
 */
final class ExceptionShortener
{
    /**
     * Maximum exception message string size in bytes
     */
    private const MAX_MESSAGE_SIZE = 400;

    /**
     * Maximum exception trace string size in bytes
     */
    private const MAX_TRACE_SIZE = 2700;

    /**
     * The length to cut on each step
     */
    private const CUT_LENGTH_STEP = 10;

    /**
     * The string that will be concatenated at the end of every trimmed string
     */
    private const TRIMMED_STRING_CAP = '...';

    /**
     * @var int Maximum exception message string size in bytes
     */
    private int $maxMessageSize;

    /**
     * @var int Maximum exception trace string size in bytes
     */
    private int $maxTraceSize;

    /**
     * @var int The length to cut on each step
     */
    private int $cutLengthStep;

    /**
     * Constructor.
     *
     * @param int $maxMessageSize Maximum exception message string size in bytes
     * @param int $maxTraceSize Maximum exception trace string size in bytes
     * @param int $cutLengthStep The length to cut on each step
     */
    public function __construct(
        int $maxMessageSize = self::MAX_MESSAGE_SIZE,
        int $maxTraceSize = self::MAX_TRACE_SIZE,
        int $cutLengthStep = self::CUT_LENGTH_STEP
    ) {
        $this->maxTraceSize = $maxTraceSize;
        $this->maxMessageSize = $maxMessageSize;
        $this->cutLengthStep = $cutLengthStep;
    }

    /**
     * Shortens the trace to the $this->maxTraceSize value and replaces the last row with the `...`.
     *
     * @param string $traceString Exception trace string
     * @return string Processed exception trace string
     */
    public function processTrace(string $traceString): string
    {
        if (mb_strlen(json_encode($traceString)) <= $this->maxTraceSize) {
            return $traceString;
        }

        if ($this->cutLengthStep < 1) {
            throw new LogicException('Cut length cannot be less than 1');
        }

        $traceString = mb_strcut($traceString, 0, $this->maxTraceSize);
        while (mb_strlen(json_encode($traceString)) > $this->maxTraceSize && $traceString !== '') {
            $traceString = $this->cutStringByStep($traceString);
        }

        $traceArray = explode("\n", $traceString);
        array_pop($traceArray);
        $traceArray[] = self::TRIMMED_STRING_CAP;

        return implode("\n", $traceArray);
    }

    /**
     * Shortens the message to the $this->maxMessageSize value and adds `...` at the end.
     *
     * @param string $message Exception message string
     * @return string Processed exception message string
     */
    public function processMessage(string $message): string
    {
        if (mb_strlen(json_encode($message)) <= $this->maxMessageSize) {
            return $message;
        }

        $message = mb_strcut($message, 0, $this->maxMessageSize);
        while (mb_strlen(json_encode($message)) > $this->maxMessageSize && $message !== '') {
            $message = $this->cutStringByStep($message);
        }

        return $message . self::TRIMMED_STRING_CAP;
    }

    /**
     * Cuts the characters from the end of the string.
     *
     * @param string $string String to be cut
     * @return string Cut string
     */
    private function cutStringByStep(string $string): string
    {
        if (mb_strlen($string) < $this->cutLengthStep) {
            return '';
        }

        return mb_strcut($string, 0, mb_strlen($string) - self::CUT_LENGTH_STEP);
    }
}
