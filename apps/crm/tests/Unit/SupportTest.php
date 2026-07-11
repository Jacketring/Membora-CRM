<?php
declare(strict_types=1);
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupportTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; $_POST = []; }

    public static function routeCases(): iterable
    {
        yield ['SUPER_ADMIN', 'platform-dashboard', true];
        yield ['SUPER_ADMIN', 'members', false];
        yield ['GYM_ADMIN', 'members', true];
        yield ['GYM_ADMIN', 'platform-dashboard', false];
        yield ['RECEPTION', 'payments', true];
        yield ['SALES', 'checkins', false];
        yield ['TRAINER', 'classes', true];
        yield ['STAFF', 'leads', false];
    }

    #[DataProvider('routeCases')]
    public function testRoutePermissions(string $role, string $route, bool $expected): void
    { self::assertSame($expected, can_access_route($route, ['role' => $role])); }

    public function testGymAdminCannotAssignPlatformRole(): void
    {
        self::assertFalse(can_perform_action('platform_assign_super_admin', ['role' => 'GYM_ADMIN']));
        self::assertTrue(can_perform_action('create_member', ['role' => 'GYM_ADMIN']));
    }

    public static function membershipCases(): iterable
    {
        yield ['WEEKLY', '2024-02-07']; yield ['MONTHLY', '2024-03-02'];
        yield ['BIMONTHLY', '2024-03-31']; yield ['QUARTERLY', '2024-05-01']; yield ['YEARLY', '2025-01-31'];
    }

    #[DataProvider('membershipCases')]
    public function testMembershipEndDate(string $period, string $expected): void
    { self::assertSame($expected, membership_end_date('2024-01-31', $period)); }

    public function testPhoneFromPost(): void
    {
        $_POST = ['phone_country' => 'ES (+34)', 'phone_number' => '612 abc 34-56'];
        self::assertSame('+34 612  34-56', phone_from_post());
        $_POST['phone_number'] = ''; self::assertNull(phone_from_post());
    }

    public function testHexColor(): void
    {
        self::assertSame('#aBc123', hex_color_or_default(' #aBc123 '));
        self::assertSame('#112233', hex_color_or_default('red', '#112233'));
    }

    public function testAuditSummaryDoesNotLeakSecrets(): void
    {
        $summary = audit_metadata_summary('{"name":"Ana","password":"secret","token":"abc","email":"ana@example.test"}');
        self::assertStringContainsString('Ana', $summary);
        self::assertStringNotContainsString('secret', $summary);
        self::assertStringNotContainsString('abc', $summary);
    }

    public function testCsrf(): void
    {
        $_SESSION['csrf_token'] = 'known-token'; $_POST['csrf_token'] = 'known-token'; self::assertTrue(verify_csrf());
        $_POST['csrf_token'] = 'wrong-token'; self::assertFalse(verify_csrf());
    }
}
