<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SimulatedCheckoutTest extends TestCase
{
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
        self::assertStringContainsString('Probar pago anual', $plansHtml);
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
