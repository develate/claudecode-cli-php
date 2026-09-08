<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Exception;

class LimitResetFailed extends ClaudeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * The OAuth token was rejected or is missing the required scope. The
     * account has to sign in again (or refresh its token) before retrying;
     * retrying with the same token cannot succeed.
     */
    public function isAuthenticationError(): bool
    {
        return $this->statusCode === 401 || $this->statusCode === 403;
    }
}
