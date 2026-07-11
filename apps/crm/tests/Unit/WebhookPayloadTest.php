<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class WebhookPayloadTest extends TestCase
{
    private ReflectionMethod $normalize;
    protected function setUp(): void { $this->normalize = new ReflectionMethod(WebhookIntegrationRepository::class, 'normalizePayload'); }

    public function testNormalizesValidPayload(): void
    {
        $result = $this->normalize->invoke(null, ['nombre' => 'Ana López', 'email' => ' ANA@EXAMPLE.COM ']);
        self::assertSame('Ana', $result['first_name']); self::assertSame('López', $result['last_name']); self::assertSame('ana@example.com', $result['email']);
    }

    public function testRejectsInvalidEmail(): void
    { $this->expectException(RuntimeException::class); $this->normalize->invoke(null, ['nombre' => 'Ana', 'email' => 'invalid']); }

    public function testRejectsIncompleteContact(): void
    { $this->expectException(RuntimeException::class); $this->normalize->invoke(null, ['nombre' => 'Ana']); }

    public function testRejectsInvalidPhone(): void
    { $this->expectException(RuntimeException::class); $this->normalize->invoke(null, ['nombre' => 'Ana', 'telefono' => '12<script>']); }
}
