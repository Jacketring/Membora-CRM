<?php

declare(strict_types=1);

final class DemoAccessPolicy
{
    public static function isEnabled(string $environment): bool
    {
        return strtolower(trim($environment)) === 'demo';
    }

    public static function isClientEnabled(string $environment): bool
    {
        return !in_array(strtolower(trim($environment)), ['test', 'testing'], true);
    }

    public static function isTypeEnabled(string $environment, string $type): bool
    {
        return $type === 'admin'
            ? self::isEnabled($environment)
            : self::isClientEnabled($environment);
    }
}
