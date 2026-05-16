<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Tests\Unit\Support;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenSalesTax\Client as SdkClient;
use OpenSalesTax\PrestaShop\Exceptions\PrestaShopOpenSalesTaxException;
use OpenSalesTax\PrestaShop\Support\CartPayloadBuilder;
use OpenSalesTax\PrestaShop\Support\ConfigBag;
use OpenSalesTax\PrestaShop\Support\OpenSalesTaxClientFactory;
use OpenSalesTax\PrestaShop\Support\RateCache;
use OpenSalesTax\PrestaShop\Support\TaxCalculator;
use OpenSalesTax\PrestaShop\Support\UrlValidator;
use OpenSalesTax\PrestaShop\Tests\Stubs\ArrayCache;
use OpenSalesTax\PrestaShop\Tests\Stubs\ArrayLogger;
use PHPUnit\Framework\TestCase;

final class TaxCalculatorTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private const PRODUCTS = [['total' => 100.00, 'cart_quantity' => 1]];

    /** @var array<string, mixed> */
    private const SHIPPING_ADDRESS = ['country_iso' => 'US', 'postcode' => '55401', 'state_iso' => 'MN'];

    public function testInactiveConfigReturnsNullImmediately(): void
    {
        $logger = new ArrayLogger();
        $calc = $this->buildCalculator(
            config: ConfigBag::fromArray([]),
            mockResponses: [],
            logger: $logger,
        );

        self::assertNull($calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD'));
        self::assertSame([], $logger->records);
    }

    public function testNonUsdReturnsNullWithoutCallingEngine(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(),
            mock: $mock,
            logger: $logger,
        );

        self::assertNull($calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'EUR'));
        self::assertSame(0, $mock->count());
    }

    public function testNonUsReturnsNullWithoutCallingEngine(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(),
            mock: $mock,
            logger: $logger,
        );

        self::assertNull($calc->calculate(
            self::PRODUCTS,
            ['country_iso' => 'GB', 'postcode' => 'SW1A 1AA'],
            'USD',
        ));
        self::assertSame(0, $mock->count());
    }

    public function testHappyPathReturnsResponseAndCachesIt(): void
    {
        $logger = new ArrayLogger();
        $store = new ArrayCache();
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->engineOk()),
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(),
            mock: $mock,
            logger: $logger,
            cacheStore: $store,
        );

        $response = $calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD');
        self::assertNotNull($response);
        self::assertSame('8.83', $response->taxTotal);
        self::assertSame(1, $store->setCount);
        self::assertSame(1, $logger->countAtLevel('info'));
        $key = array_key_first($store->store);
        self::assertNotNull($key);
        self::assertMatchesRegularExpression('/^ostax:rate:55401:[0-9a-f]{16}$/', $key);
    }

    public function testSecondCallHitsCacheWithoutEngineRoundTrip(): void
    {
        $logger = new ArrayLogger();
        $store = new ArrayCache();
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->engineOk()),
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(),
            mock: $mock,
            logger: $logger,
            cacheStore: $store,
        );

        $first = $calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD');
        $second = $calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD');

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($first->taxTotal, $second->taxTotal);
        self::assertSame(1, $logger->countAtLevel('info'));
    }

    public function testEngineErrorWithFailSoftReturnsNullAndLogsWarning(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([
            new Response(500, ['Content-Type' => 'application/json'], '{"error":"oops"}'),
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(failHard: false),
            mock: $mock,
            logger: $logger,
        );

        self::assertNull($calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD'));
        self::assertSame(1, $logger->countAtLevel('warning'));
    }

    public function testEngineErrorWithFailHardThrows(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([
            new Response(500, ['Content-Type' => 'application/json'], '{"error":"oops"}'),
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(failHard: true),
            mock: $mock,
            logger: $logger,
        );

        $this->expectException(PrestaShopOpenSalesTaxException::class);
        $calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD');
    }

    public function testPrivateBaseUrlFailSoftReturnsNull(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([]);
        $config = ConfigBag::fromArray([
            'enabled'  => true,
            'base_url' => 'http://10.0.0.1:8080',
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $config,
            mock: $mock,
            logger: $logger,
            validator: new UrlValidator(false, static fn (string $h): array => [$h]),
        );

        self::assertNull($calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD'));
        self::assertSame(0, $mock->count());
    }

    public function testEmptyCartReturnsNull(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(),
            mock: $mock,
            logger: $logger,
        );

        self::assertNull($calc->calculate([], self::SHIPPING_ADDRESS, 'USD'));
        self::assertSame(0, $mock->count());
    }

    public function testTwoCartsWithDifferentSignaturesBothHitEngine(): void
    {
        $logger = new ArrayLogger();
        $store = new ArrayCache();
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->engineOk()),
            new Response(200, ['Content-Type' => 'application/json'], $this->engineOk()),
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(),
            mock: $mock,
            logger: $logger,
            cacheStore: $store,
        );

        $cart1 = [['total' => 100.00]];
        $cart2 = [['total' => 50.00], ['total' => 50.00]];

        $r1 = $calc->calculate($cart1, self::SHIPPING_ADDRESS, 'USD');
        $r2 = $calc->calculate($cart2, self::SHIPPING_ADDRESS, 'USD');

        self::assertNotNull($r1);
        self::assertNotNull($r2);
        self::assertSame(2, $store->setCount);
        self::assertSame(2, $logger->countAtLevel('info'));
        self::assertCount(2, $store->store);
    }

    public function testNexusFilterAllowsConfiguredState(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->engineOk()),
        ]);
        $config = ConfigBag::fromArray([
            'enabled'               => true,
            'base_url'              => 'https://ost.example.com',
            'allow_private_nets'    => false,
            'nexus_filter_enabled'  => true,
            'nexus_state_allowlist' => 'MN, CA',
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $config,
            mock: $mock,
            logger: $logger,
        );

        $response = $calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD');
        self::assertNotNull($response);
    }

    public function testNexusFilterBlocksUnconfiguredState(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([]);
        $config = ConfigBag::fromArray([
            'enabled'               => true,
            'base_url'              => 'https://ost.example.com',
            'allow_private_nets'    => false,
            'nexus_filter_enabled'  => true,
            'nexus_state_allowlist' => 'CA',
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $config,
            mock: $mock,
            logger: $logger,
        );

        // Ship-to MN, but only CA is in the allowlist.
        self::assertNull($calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD'));
        self::assertSame(0, $mock->count());
    }

    public function testNexusFilterBlocksMissingState(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([]);
        $config = ConfigBag::fromArray([
            'enabled'               => true,
            'base_url'              => 'https://ost.example.com',
            'allow_private_nets'    => false,
            'nexus_filter_enabled'  => true,
            'nexus_state_allowlist' => 'MN',
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $config,
            mock: $mock,
            logger: $logger,
        );

        // Address has no state_iso → can't determine nexus → block.
        $address = ['country_iso' => 'US', 'postcode' => '55401']; // no state_iso
        self::assertNull($calc->calculate(self::PRODUCTS, $address, 'USD'));
        self::assertSame(0, $mock->count());
    }

    public function testNexusFilterDisabledIgnoresState(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->engineOk()),
        ]);
        $config = ConfigBag::fromArray([
            'enabled'               => true,
            'base_url'              => 'https://ost.example.com',
            'allow_private_nets'    => false,
            'nexus_filter_enabled'  => false,
            'nexus_state_allowlist' => 'CA', // ignored when filter off
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $config,
            mock: $mock,
            logger: $logger,
        );

        $response = $calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD');
        self::assertNotNull($response);
    }

    public function testEngineMalformedJsonFailsSoftByDefault(): void
    {
        $logger = new ArrayLogger();
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], 'not json'),
        ]);
        $calc = $this->buildCalculatorWithMock(
            config: $this->activeConfig(),
            mock: $mock,
            logger: $logger,
        );

        self::assertNull($calc->calculate(self::PRODUCTS, self::SHIPPING_ADDRESS, 'USD'));
        self::assertSame(1, $logger->countAtLevel('warning'));
    }

    public function testGetConfigExposesUnderlyingBag(): void
    {
        $config = $this->activeConfig();
        $calc = $this->buildCalculator(config: $config, mockResponses: [], logger: new ArrayLogger());
        self::assertSame($config, $calc->getConfig());
    }

    private function activeConfig(bool $failHard = false): ConfigBag
    {
        return ConfigBag::fromArray([
            'enabled'            => true,
            'base_url'           => 'https://ost.example.com',
            'fail_hard'          => $failHard,
            'cache_ttl_seconds'  => 60,
            'allow_private_nets' => false,
        ]);
    }

    /**
     * @param array<int, mixed> $mockResponses
     */
    private function buildCalculator(
        ConfigBag $config,
        array $mockResponses,
        ArrayLogger $logger,
    ): TaxCalculator {
        $mock = new MockHandler($mockResponses);
        return $this->buildCalculatorWithMock($config, $mock, $logger);
    }

    private function buildCalculatorWithMock(
        ConfigBag $config,
        MockHandler $mock,
        ArrayLogger $logger,
        ?ArrayCache $cacheStore = null,
        ?UrlValidator $validator = null,
    ): TaxCalculator {
        $cacheStore ??= new ArrayCache();
        $validator ??= new UrlValidator(
            allowPrivateNets: false,
            hostResolver: static fn (string $host): array => ['8.8.8.8'],
        );

        $handler = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handler]);

        $factory = new class ($logger, $validator, $guzzle) extends OpenSalesTaxClientFactory {
            public function __construct(
                ArrayLogger $log,
                UrlValidator $validator,
                private readonly GuzzleClient $guzzle,
            ) {
                parent::__construct($log, $validator);
            }

            public function make(ConfigBag $config): ?SdkClient
            {
                $real = parent::make($config);
                if ($real === null) {
                    return null;
                }
                return new SdkClient(
                    baseUrl: $config->baseUrl,
                    apiKey: $config->apiKey !== '' ? $config->apiKey : null,
                    httpClient: $this->guzzle,
                );
            }
        };

        return new TaxCalculator(
            config: $config,
            clientFactory: $factory,
            payloadBuilder: new CartPayloadBuilder(),
            cache: new RateCache($cacheStore, $config->cacheTtlSeconds),
            logger: $logger,
        );
    }

    private function engineOk(): string
    {
        return json_encode([
            'subtotal'   => '100.00',
            'tax_total'  => '8.83',
            'lines'      => [
                [
                    'amount'        => '100.00',
                    'category'      => 'general',
                    'tax'           => '8.83',
                    'rate_pct'      => '8.83',
                    'jurisdictions' => [
                        ['name' => 'Minnesota State', 'type' => 'state',  'rate_pct' => '6.875', 'tax' => '6.88'],
                        ['name' => 'Hennepin County', 'type' => 'county', 'rate_pct' => '1.955', 'tax' => '1.95'],
                    ],
                ],
            ],
            'disclaimer' => 'calc-only',
        ], JSON_THROW_ON_ERROR);
    }
}
