<?php

declare(strict_types=1);

final class DemoAccessPolicy
{
    public static function isEnabled(string $environment): bool
    {
        return strtolower(trim($environment)) === 'demo';
    }
}
