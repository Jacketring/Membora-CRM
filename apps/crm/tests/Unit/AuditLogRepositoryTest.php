<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuditLogRepositoryTest extends TestCase
{
    public function testOnlyRequestedClientActivityIsVisible(): void
    {
        self::assertSame([
            '' => 'Todas',
            'leads' => 'Leads',
            'users' => 'Usuarios',
            'members' => 'Socios',
            'memberships' => 'Membresias',
            'billing' => 'Facturacion',
            'checkins' => 'Check-ins',
            'classes' => 'Clases',
            'tasks' => 'Tareas',
            'alerts' => 'Alertas',
        ], AuditLogRepository::actionOptions(null));

        $method = new ReflectionMethod(AuditLogRepository::class, 'isBusinessAction');

        self::assertTrue($method->invoke(null, 'create_lead'));
        self::assertTrue($method->invoke(null, 'update_member'));
        self::assertTrue($method->invoke(null, 'create_client_invoice'));
        self::assertTrue($method->invoke(null, 'update_risk_alert_status'));

        self::assertFalse($method->invoke(null, 'view_audit'));
        self::assertFalse($method->invoke(null, 'enter_empresa_crm'));
        self::assertFalse($method->invoke(null, 'exit_empresa_crm'));
        self::assertFalse($method->invoke(null, 'create_platform_user'));
        self::assertFalse($method->invoke(null, 'delete_member'));
        self::assertFalse($method->invoke(null, 'login'));
    }

    public function testCardFieldsAreAlwaysRedactedFromAuditMetadata(): void
    {
        $method = new ReflectionMethod(AuditLogRepository::class, 'sanitizePayload');
        $payload = $method->invoke(null, [
            'plan_code' => 'PRO',
            'card_number' => '4242424242424242',
            'card_expiry' => '12/30',
            'card_cvc' => '123',
        ]);

        self::assertSame('PRO', $payload['plan_code']);
        self::assertSame('[redacted]', $payload['card_number']);
        self::assertSame('[redacted]', $payload['card_expiry']);
        self::assertSame('[redacted]', $payload['card_cvc']);
    }
}
