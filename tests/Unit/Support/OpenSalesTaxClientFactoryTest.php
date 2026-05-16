<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Tests\Unit\Support;

use OpenSalesTax\PrestaShop\Exceptions\ConfigurationException;
use OpenSalesTax\PrestaShop\Support\ConfigBag;
use OpenSalesTax\PrestaShop\Support\OpenSalesTaxClientFactory;
use OpenSalesTax\PrestaShop\Support\UrlValidator;
use OpenSalesTax\PrestaShop\Tests\Stubs\ArrayLogger;
use PHPUnit\Framework\TestCase;

final class OpenSalesTaxClientFactoryTest extends TestCase
{
    public function testEmptyBaseUrlReturnsNull(): void
    {
        $factory = new OpenSalesTaxClientFactory(new ArrayLogger());
        self::assertNull($factory->make(ConfigBag::fromArray(['enabled' => true])));
    }

    public function testValidPublicUrlReturnsClient(): void
    {
        $logger = new ArrayLogger();
        $factory = new OpenSalesTaxClientFactory(
            $logger,
            new UrlValidator(false, static fn (string $h): array => ['8.8.8.8']),
        );
        $config = ConfigBag::fromArray([
            'enabled'  => true,
            'base_url' => 'https://ost.example.com',
        ]);

        self::assertNotNull($factory->make($config));
        self::assertSame([], $logger->records);
    }

    public function testPrivateUrlFailSoftReturnsNullAndLogs(): void
    {
        $logger = new ArrayLogger();
        $factory = new OpenSalesTaxClientFactory(
            $logger,
            new UrlValidator(false, static fn (string $h): array => [$h]),
        );
        $config = ConfigBag::fromArray([
            'enabled'  => true,
            'base_url' => 'http://10.0.0.1:8080',
            'fail_hard' => false,
        ]);

        self::assertNull($factory->make($config));
        self::assertSame(1, $logger->countAtLevel('warning'));
    }

    public function testPrivateUrlFailHardThrows(): void
    {
        $logger = new ArrayLogger();
        $factory = new OpenSalesTaxClientFactory(
            $logger,
            new UrlValidator(false, static fn (string $h): array => [$h]),
        );
        $config = ConfigBag::fromArray([
            'enabled'   => true,
            'base_url'  => 'http://10.0.0.1:8080',
            'fail_hard' => true,
        ]);

        $this->expectException(ConfigurationException::class);
        $factory->make($config);
    }
}
