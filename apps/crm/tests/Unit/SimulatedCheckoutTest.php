<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SimulatedCheckoutTest extends TestCase
{
    public function testStripeIsTheSafeDefaultCheckoutProvider(): void
    {
        $previous = getenv('CHECKOUT_PROVIDER');
        putenv('CHECKOUT_PROVIDER');

        try {
            self::assertSame('stripe', StripeBillingConfig::checkoutProvider());
            self::assertFalse(StripeBillingConfig::simulatedCheckoutEnabled());
        } finally {
            if ($previous === false) {
                putenv('CHECKOUT_PROVIDER');
            } else {
                putenv('CHECKOUT_PROVIDER=' . $previous);
            }
        }
    }

    public function testAcceptsOnlyTheDocumentedFakeCard(): void
    {
        $futureYear = (int) date('y') + 2;
        SimulatedCheckoutService::validateCard('4242 4242 4242 4242', '12/' . $futureYear, '123');

        self::addToAssertionCount(1);

        $empresa = ['name' => 'Gimnasio Demo', 'plan' => 'TRIAL', 'status' => 'TRIAL'];
        $plan = ['code' => 'PRO', 'name' => 'Pro'];
        $renewalPeriod = 'MONTHLY';
        $checkoutAmount = '89.00';
        ob_start();
        require dirname(__DIR__, 2) . '/src/Views/simulated-checkout.php';
        $checkoutHtml = (string) ob_get_clean();
        self::assertStringContainsString('4242 4242 4242 4242', $checkoutHtml);
        self::assertStringContainsString('complete_tenant_simulated_checkout', $checkoutHtml);

        $accessState = ['remaining_days' => 10];
        $simulatedCheckout = true;
        $stripeReady = false;
        $canPurchase = true;
        $plans = [[
            'code' => 'PRO', 'name' => 'Pro', 'monthly_price' => '89.00', 'original_monthly_price' => null,
            'discount_label' => null, 'features' => [], 'max_users' => 8, 'max_members' => 1000,
            'stripe_monthly_available' => false, 'stripe_annual_available' => false,
        ]];
        ob_start();
        require dirname(__DIR__, 2) . '/src/Views/upgrade-plan.php';
        $plansHtml = (string) ob_get_clean();
        self::assertStringContainsString('open_tenant_simulated_checkout', $plansHtml);
        self::assertStringContainsString('Mejorar con pago anual', $plansHtml);
    }

    public function testPaidClientSeesCurrentPlanAndCanOnlyUpgrade(): void
    {
        self::assertTrue(PlatformPlanRepository::canUpgrade('BASIC', 'PRO'));
        self::assertTrue(PlatformPlanRepository::canUpgrade('BASIC', 'ENTERPRISE'));
        self::assertFalse(PlatformPlanRepository::canUpgrade('BASIC', 'BASIC'));
        self::assertFalse(PlatformPlanRepository::canUpgrade('PRO', 'BASIC'));
        self::assertFalse(PlatformPlanRepository::canUpgrade('ENTERPRISE', 'ENTERPRISE'));

        $empresa = ['name' => 'Gimnasio Demo', 'plan' => 'BASIC', 'status' => 'ACTIVE', 'stripe_subscription_id' => null];
        $accessState = ['remaining_days' => 0];
        $simulatedCheckout = false;
        $stripeReady = true;
        $canPurchase = true;
        $plans = [
            [
                'code' => 'BASIC', 'name' => 'Basic', 'monthly_price' => '49.00', 'original_monthly_price' => null,
                'discount_label' => null, 'features' => [], 'max_users' => 3, 'max_members' => 300,
                'stripe_monthly_available' => true, 'stripe_annual_available' => false,
            ],
            [
                'code' => 'PRO', 'name' => 'Pro', 'monthly_price' => '89.00', 'original_monthly_price' => null,
                'discount_label' => null, 'features' => [], 'max_users' => 8, 'max_members' => 1000,
                'stripe_monthly_available' => true, 'stripe_annual_available' => false,
            ],
        ];

        ob_start();
        require dirname(__DIR__, 2) . '/src/Views/upgrade-plan.php';
        $plansHtml = (string) ob_get_clean();

        self::assertStringContainsString('PLAN ACTUAL', $plansHtml);
        self::assertStringContainsString('Tu plan actual es BASIC', $plansHtml);
        self::assertStringContainsString('Elegir pago mensual', $plansHtml);
        self::assertStringContainsString('create_tenant_stripe_checkout', $plansHtml);
        self::assertStringNotContainsString('Pendiente de configurar el Price ID en Stripe', $plansHtml);
    }

    public function testRejectsARealOrUnknownCardNumber(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No introduzcas una tarjeta real');

        SimulatedCheckoutService::validateCard('5555 5555 5555 4444', '12/30', '123');
    }

    public function testRejectsAnExpiredFakeCard(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fecha de caducidad ficticia futura');

        SimulatedCheckoutService::validateCard('4242 4242 4242 4242', '01/20', '123');
    }
}
