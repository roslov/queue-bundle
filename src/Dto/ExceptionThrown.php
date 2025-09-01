<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Dto;

/**
 * Event DTO: Exception was thrown.
 */
final class ExceptionThrown
{
    /**
     * @var string Exception class name
     */
    private string $exceptionClass;

    /**
     * @var string File path with error
     */
    private string $file;

    /**
     * @var int Line of code with error
     */
    private int $line;

    /**
     * @var string Exception message
     */
    private string $message;

    /**
     * @var string Exception trace
     */
    private string $trace;

    /**
     * @var string Service name
     */
    private string $serviceName;

    /**
     * @var string|null Host name
     */
    private ?string $hostName = null;

    /**
     * @var string|null Request URI (it’s a path with a query string in most cases, if available)
     */
    private ?string $uri = null;

    /**
     * Returns exception class name.
     *
     * @return string Exception class name
     */
    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    /**
     * Sets exception class name.
     *
     * @param string $exceptionClass Exception class name
     */
    public function setExceptionClass(string $exceptionClass): void
    {
        $this->exceptionClass = $exceptionClass;
    }

    /**
     * Returns file name with the error.
     *
     * @return string File name with the error
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Sets file name with the error.
     *
     * @param string $file File name with the error
     */
    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    /**
     * Returns line of code with the error.
     *
     * @return int Line of code with the error
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Sets line of code with the error.
     *
     * @param int $line Line of code with the error
     */
    public function setLine(int $line): void
    {
        $this->line = $line;
    }

    /**
     * Returns the message of the exception.
     *
     * @return string Exception message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Sets the message of the exception.
     *
     * @param string $message Exception message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * Returns the trace of the exception.
     *
     * @return string Exception trace
     */
    public function getTrace(): string
    {
        return $this->trace;
    }

    /**
     * Sets the trace of the exception.
     *
     * @param string $trace Exception trace
     */
    public function setTrace(string $trace): void
    {
        $this->trace = $trace;
    }

    /**
     * Returns the name of the service.
     *
     * @return string Service name
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * Sets the name of the service.
     *
     * @param string $serviceName Service name
     */
    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * Returns the name of the host.
     *
     * @return string|null Host name
     */
    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    /**
     * Sets the name of the host.
     *
     * @param string|null $hostName Host name
     */
    public function setHostName(?string $hostName): void
    {
        $this->hostName = $hostName;
    }

    /**
     * Returns the request URI.
     *
     * @return string|null URI
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * Sets the request URI.
     *
     * @param string|null $uri URI
     */
    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }
}
