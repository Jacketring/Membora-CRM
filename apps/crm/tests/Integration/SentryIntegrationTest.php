<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SentryIntegrationTest extends TestCase
{
    public function testSdkIsInitializedWithoutSendingPii(): void
    {
        \Sentry\init([
            'dsn' => 'http://public@127.0.0.1:9/1',
            'environment' => 'test',
            'send_default_pii' => false,
            'default_integrations' => false,
        ]);

        $client = \Sentry\SentrySdk::getCurrentHub()->getClient();
        self::assertNotNull($client);
        self::assertFalse($client->getOptions()->shouldSendDefaultPii());
        self::assertSame('test', $client->getOptions()->getEnvironment());
    }
}
