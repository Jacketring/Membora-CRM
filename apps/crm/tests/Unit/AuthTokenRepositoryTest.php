<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthTokenRepositoryTest extends TestCase
{
    public function testTokenFormatRequiresSelectorAndVerifier(): void
    {
        $method = new ReflectionMethod(AuthTokenRepository::class, 'tokenParts');
        $selector = str_repeat('a', 18);
        $verifier = str_repeat('b', 64);

        self::assertSame([$selector, $verifier], $method->invoke(null, $selector . '.' . $verifier));
        self::assertNull($method->invoke(null, 'invalid-token'));
        self::assertNull($method->invoke(null, $selector . '.' . str_repeat('x', 64)));
    }

    public function testPasswordRecoveryActionsArePublic(): void
    {
        self::assertTrue(can_perform_action('request_password_reset', null));
        self::assertTrue(can_perform_action('reset_password', null));
        self::assertTrue(can_perform_action('confirm_trial_activation', null));
    }
}
