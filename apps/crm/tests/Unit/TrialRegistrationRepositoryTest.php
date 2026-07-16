<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TrialRegistrationRepositoryTest extends TestCase
{
    public function testValidTrialPayloadHasNoValidationErrors(): void
    {
        self::assertSame([], TrialRegistrationRepository::validationErrors([
            'nombre' => 'Ana Martín',
            'empresa' => 'Centro Norte',
            'email' => 'josehur2003+prueba@gmail.com',
            'acepta_rgpd' => '1',
        ]));
    }

    public function testTrialIsRestrictedToTheAuthorizedGmailInbox(): void
    {
        self::assertTrue(TrialRegistrationRepository::isAllowedRecipient('josehur2003@gmail.com'));
        self::assertTrue(TrialRegistrationRepository::isAllowedRecipient('josehur2003+otra-prueba@gmail.com'));
        self::assertFalse(TrialRegistrationRepository::isAllowedRecipient('otro@gmail.com'));
        self::assertFalse(TrialRegistrationRepository::isAllowedRecipient('josehur2003@example.com'));
    }

    public function testTrialPayloadRequiresIdentityCompanyEmailAndConsent(): void
    {
        $errors = TrialRegistrationRepository::validationErrors([
            'nombre' => '',
            'empresa' => '',
            'email' => 'correo-invalido',
            'acepta_rgpd' => '',
        ]);

        self::assertCount(4, $errors);
        self::assertContains('Indica tu nombre.', $errors);
        self::assertContains('Indica el nombre de tu gimnasio.', $errors);
        self::assertContains('Indica un email válido.', $errors);
        self::assertContains('Debes aceptar la política de privacidad.', $errors);
    }

    public function testHoneypotIsAcceptedWithoutProvisioningAnything(): void
    {
        self::assertSame(
            ['success' => true, 'message' => 'Revisa tu correo para continuar.'],
            TrialRegistrationRepository::request(['website' => 'https://spam.example'])
        );
    }

    public function testProvisioningCreatesLinkedCustomerCompanyAndFourteenDayTrial(): void
    {
        $data = TrialRegistrationRepository::provisioningData([
            'name' => 'Ana Martín',
            'company_name' => 'Centro Norte',
            'email' => 'ana@example.com',
        ], 'client_trial_1', 'temporary-secret');

        self::assertSame('client_trial_1', $data['client_id']);
        self::assertSame('Centro Norte', $data['name']);
        self::assertSame('ana@example.com', $data['contact_email']);
        self::assertSame('TRIAL', $data['plan']);
        self::assertSame('TRIAL', $data['status']);
        self::assertSame('TRIAL', $data['payment_status']);
        self::assertSame('0', $data['monthly_price']);
        self::assertSame('14', $data['trial_days']);
        self::assertSame('1', $data['create_tenant']);
        self::assertSame('Ana Martín', $data['admin_name']);
        self::assertSame('temporary-secret', $data['admin_password']);
    }
}
