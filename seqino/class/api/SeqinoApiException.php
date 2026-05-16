<?php

declare(strict_types=1);

/**
 * Dedicated exception for Seqino API failures.
 */
class SeqinoApiException extends RuntimeException
{
    /**
     * @var array<string,mixed>
     */
    private array $context;

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, array $context = array())
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
