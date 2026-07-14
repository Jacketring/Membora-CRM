<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ComprehensiveSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = $_POST = $_SESSION = $_SERVER = [];
    }

    public function testEscapingFormattingAndIdentityHelpers(): void
    {
        self::assertSame('&lt;b&gt;', e('<b>'));
        self::assertSame('Sin fecha', format_date(null));
        self::assertSame('02/01/2026 03:04', format_date('2026-01-02 03:04:00'));
        self::assertSame('02/01/2026', format_date_short('2026-01-02'));
        self::assertSame('1.234,50 EUR', money_amount(1234.5));
        self::assertSame('AL', initials('Ana', 'López'));
        self::assertSame('S', initials(null));
        self::assertMatchesRegularExpression('/^[a-z0-9_]{20,}$/', cuid());
    }

    public function testCountryAndPhoneHelpersCoverKnownAndUnknownPrefixes(): void
    {
        self::assertGreaterThan(10, count(country_dial_codes()));
        self::assertSame('España', country_dial_options()[0]['country']);
        self::assertSame('+351', phone_country_value('+351 912 345 678'));
        self::assertSame('+34', phone_country_value('612345678'));
        self::assertSame('912 345 678', phone_local_value('+351 912 345 678'));
        self::assertSame('', phone_local_value(''));
        self::assertSame('+34', phone_country_entry('unknown')['code']);
    }

    public function testSessionFlashTokensAndCsrfHelpers(): void
    {
        flash('Guardado', 'success');
        self::assertSame(['message' => 'Guardado', 'type' => 'success'], flash());
        self::assertNull(flash());

        $token = form_token('lead');
        self::assertTrue(consume_form_token('lead', $token));
        self::assertFalse(consume_form_token('lead', $token));
        self::assertFalse(consume_form_token('missing', ''));

        $csrf = csrf_token();
        self::assertSame($csrf, csrf_token());
        self::assertStringContainsString($csrf, csrf_field());
        $_POST['csrf_token'] = $csrf;
        self::assertTrue(verify_csrf());
        self::assertStringContainsString('csrf_token', inject_csrf_fields('<form method="post"></form>'));
        self::assertSame('<form method="get"></form>', inject_csrf_fields('<form method="get"></form>'));
    }

    public function testRoleAndPermissionMatrices(): void
    {
        $platform = ['role' => 'SUPER_ADMIN'];
        $gym = ['role' => 'GYM_ADMIN'];
        self::assertTrue(is_platform_admin($platform));
        self::assertFalse(is_platform_admin(['role' => 'SUPER_ADMIN', 'tenant_context' => true]));
        self::assertSame('GYM_ADMIN', user_role_key($gym));
        self::assertTrue(is_gym_admin($gym));
        self::assertTrue(can_access_route('login', ['role' => 'STAFF']));
        self::assertTrue(can_access_route('platform-plans', $platform));
        self::assertFalse(can_access_route('members', $platform));
        self::assertTrue(can_access_route('members', $gym));
        self::assertFalse(can_access_route('platform-plans', $gym));

        foreach (['SALES_RECEPTION', 'RECEPTION', 'SALES', 'TRAINER', 'STAFF', 'UNKNOWN'] as $role) {
            self::assertTrue(can_access_route('dashboard', ['role' => $role]));
            self::assertTrue(can_perform_action('update_profile', ['role' => $role]));
        }
        self::assertTrue(can_perform_action('platform_create_plan', $platform));
        self::assertTrue(can_perform_action('create_platform_user', $platform));
        self::assertTrue(can_perform_action('update_platform_user', $platform));
        self::assertTrue(can_perform_action('delete_platform_user', $platform));
        self::assertFalse(can_perform_action('create_member', $platform));
        self::assertTrue(can_perform_action('create_member', $gym));
        self::assertFalse(can_perform_action('platform_create_plan', $gym));
        self::assertFalse(can_perform_action('create_platform_user', $gym));
        self::assertFalse(can_perform_action('update_platform_user', $gym));
        self::assertFalse(can_perform_action('delete_platform_user', $gym));
        self::assertTrue(can_perform_action('create_payment', ['role' => 'RECEPTION']));
        self::assertFalse(can_perform_action('delete_user', ['role' => 'RECEPTION']));
        self::assertTrue(can_perform_action('create_lead', ['role' => 'SALES']));
        self::assertTrue(can_perform_action('create_reservation', ['role' => 'TRAINER']));
        self::assertFalse(can_perform_action('create_payment', ['role' => 'STAFF']));
        self::assertTrue(can_perform_action('keep_demo_session', null));
        self::assertTrue(can_perform_action('schedule_demo_cleanup', null));
    }

    public function testEveryVisibleLabelFamilyHasFallbacks(): void
    {
        $calls = [
            [status_label(...), 'ACTIVE'], [role_label(...), 'RECEPTION'], [empresa_status_label(...), 'TRIAL'],
            [empresa_payment_status_label(...), 'OVERDUE'], [empresa_renewal_period_label(...), 'ANNUAL'],
            [empresa_renewal_status_label(...), 'CANCELLED'], [platform_client_status_label(...), 'CUSTOMER'],
            [platform_lead_status_label(...), 'CONVERTED'], [platform_payment_status_label(...), 'PAID'],
            [platform_invoice_status_label(...), 'ISSUED'], [payment_method_label(...), 'CARD'],
            [billing_sync_status_label(...), 'SUCCESS'], [billing_operation_label(...), 'CREATE'],
            [checkin_method_label(...), 'QR'], [checkin_method_label(...), 'AUTOMATIC'], [risk_alert_type_label(...), 'INACTIVITY'],
            [risk_alert_severity_label(...), 'HIGH'], [audit_action_label(...), 'create_lead'],
            [audit_entity_label(...), 'member'], [audit_area_label(...), 'settings'],
            [platform_plan_status_label(...), 'ACTIVE'], [webhook_status_label(...), 'success'],
            [source_label(...), 'WEB'], [task_type_label(...), 'CALL'], [membership_period_label(...), 'YEARLY'],
        ];
        foreach ($calls as [$callable, $value]) {
            self::assertNotSame('', $callable($value));
            self::assertNotSame('', $callable('__UNKNOWN__'));
        }
        self::assertSame('Por defecto', enum_label('x', [], 'Por defecto'));
    }

    public function testAuditMetadataCoversInvalidEmptyBlockedAndVisibleValues(): void
    {
        self::assertSame('Sin detalles visibles', audit_metadata_summary(null));
        self::assertSame('Detalle interno oculto', audit_metadata_summary('{bad json'));
        self::assertSame('Detalle interno oculto', audit_metadata_summary('{"password":"secret"}'));
        $summary = audit_metadata_summary('{"name":"Ana","amount":12.5,"active":true,"nested":{"x":1},"empty":""}');
        self::assertStringContainsString('Ana', $summary);
        self::assertStringContainsString('12', $summary);
        self::assertStringNotContainsString('secret', $summary);
    }

    public function testTimeStageSourceMembershipAndMonthRules(): void
    {
        self::assertSame('--:--', format_time(null));
        self::assertSame('09:30', format_time('09:30:00'));
        self::assertNotSame('', stage_color_class('NEW'));
        self::assertNotSame('', stage_color_class('UNKNOWN'));
        self::assertSame(7, membership_duration_days('WEEKLY'));
        self::assertSame(60, membership_duration_days('BIMONTHLY'));
        self::assertSame(90, membership_duration_days('QUARTERLY'));
        self::assertSame(365, membership_duration_days('YEARLY'));
        self::assertSame(30, membership_duration_days('MONTHLY'));
        self::assertSame('Julio 2026', month_title('2026-07'));
    }

    public function testRequestOriginRules(): void
    {
        self::assertTrue(request_origin_allowed());
        $_SERVER = [
            'HTTP_HOST' => 'localhost:8000',
            'HTTP_ORIGIN' => 'http://localhost:8000',
            'SCRIPT_NAME' => '/app/index.php',
        ];
        self::assertTrue(request_origin_allowed());
        self::assertSame('http://localhost:8000/app', app_base_url());
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        self::assertFalse(request_origin_allowed());
    }

    public function testProductionClientDemoAcceptsEveryConfiguredPublicOrigin(): void
    {
        $previousEnvironment = getenv('APP_ENV');
        $previousWebUrl = getenv('WEB_APP_URL');

        try {
            putenv('APP_ENV=production');
            putenv('WEB_APP_URL=https://membora.es,https://www.membora.es');
            $_POST = ['action' => 'demo_login', 'demo_type' => 'client'];
            $_SERVER['HTTP_ORIGIN'] = 'https://www.membora.es';

            self::assertTrue(demo_origin_allowed());

            $_POST['demo_type'] = 'admin';
            self::assertFalse(demo_origin_allowed());

            $_POST['demo_type'] = 'client';
            $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
            self::assertFalse(demo_origin_allowed());
        } finally {
            $previousEnvironment === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $previousEnvironment);
            $previousWebUrl === false ? putenv('WEB_APP_URL') : putenv('WEB_APP_URL=' . $previousWebUrl);
        }
    }
}
