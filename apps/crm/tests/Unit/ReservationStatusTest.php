<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReservationStatusTest extends TestCase
{
    public function testLegacyUppercaseStatusesAreNormalized(): void
    {
        $method = new ReflectionMethod(ReservationRepository::class, 'normalizeStatus');
        self::assertSame('reserved', $method->invoke(null, 'RESERVED'));
        self::assertSame('attended', $method->invoke(null, ' Attended '));
        self::assertSame('no_show', $method->invoke(null, 'NO_SHOW'));
    }
}
