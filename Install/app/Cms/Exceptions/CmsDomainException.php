<?php

namespace App\Cms\Exceptions;

use RuntimeException;

class CmsDomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        protected int $status = 422,
        protected array $context = []
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
