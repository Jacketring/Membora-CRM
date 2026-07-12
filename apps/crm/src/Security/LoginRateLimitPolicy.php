<?php

declare(strict_types=1);

final class LoginRateLimitPolicy
{
    public function __construct(
        private readonly int $maximumFailures = 5,
        private readonly int $windowSeconds = 900,
    ) {
    }

    /** @param list<int> $failedAt */
    public function failuresInWindow(array $failedAt, int $now): int
    {
        $cutoff = $now - $this->windowSeconds;

        return count(array_filter($failedAt, static fn (int $timestamp): bool => $timestamp >= $cutoff));
    }

    /** @param list<int> $failedAt */
    public function isBlocked(array $failedAt, int $now): bool
    {
        return $this->failuresInWindow($failedAt, $now) >= $this->maximumFailures;
    }
}
