<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Tests\Unit\Support;

use OpenSalesTax\PrestaShop\Support\ConfigBag;
use PHPUnit\Framework\TestCase;

final class ConfigBagTest extends TestCase
{
    public function testDefaultsAreSafe(): void
    {
        $bag = ConfigBag::fromArray([]);

        self::assertFalse($bag->enabled);
        self::assertSame('', $bag->baseUrl);
        self::assertSame('', $bag->apiKey);
        self::assertSame(10.0, $bag->timeoutSeconds);
        self::assertTrue($bag->tlsVerify);
        self::assertFalse($bag->allowPrivateNets);
        self::assertFalse($bag->failHard);
        self::assertSame(3600, $bag->cacheTtlSeconds);
        self::assertFalse($bag->nexusFilterEnabled);
        self::assertSame([], $bag->nexusStateAllowlist);
        self::assertFalse($bag->isActive());
    }

    public function testIsActiveRequiresBothEnabledAndBaseUrl(): void
    {
        self::assertFalse(ConfigBag::fromArray(['enabled' => true])->isActive());
        self::assertFalse(ConfigBag::fromArray(['base_url' => 'https://x'])->isActive());
        self::assertTrue(
            ConfigBag::fromArray(['enabled' => true, 'base_url' => 'https://x'])->isActive(),
        );
    }

    public function testBoolishCoercion(): void
    {
        self::assertTrue(ConfigBag::fromArray(['enabled' => 'yes'])->enabled);
        self::assertTrue(ConfigBag::fromArray(['enabled' => '1'])->enabled);
        self::assertTrue(ConfigBag::fromArray(['enabled' => 'on'])->enabled);
        self::assertTrue(ConfigBag::fromArray(['enabled' => 'true'])->enabled);
        self::assertFalse(ConfigBag::fromArray(['enabled' => 'no'])->enabled);
        self::assertFalse(ConfigBag::fromArray(['enabled' => '0'])->enabled);
        self::assertFalse(ConfigBag::fromArray(['enabled' => 'random'])->enabled);
    }

    public function testFloatishCoercion(): void
    {
        self::assertSame(5.5, ConfigBag::fromArray(['timeout_seconds' => '5.5'])->timeoutSeconds);
        self::assertSame(7.0, ConfigBag::fromArray(['timeout_seconds' => 7])->timeoutSeconds);
        self::assertSame(10.0, ConfigBag::fromArray(['timeout_seconds' => 'nope'])->timeoutSeconds);
    }

    public function testIntishCoercion(): void
    {
        self::assertSame(60, ConfigBag::fromArray(['cache_ttl_seconds' => '60'])->cacheTtlSeconds);
        self::assertSame(60, ConfigBag::fromArray(['cache_ttl_seconds' => 60])->cacheTtlSeconds);
        self::assertSame(3600, ConfigBag::fromArray(['cache_ttl_seconds' => 'nope'])->cacheTtlSeconds);
    }

    public function testStringishStripsWhitespace(): void
    {
        self::assertSame('https://ost.example.com', ConfigBag::fromArray([
            'base_url' => '  https://ost.example.com  ',
        ])->baseUrl);
    }

    public function testNexusStateAllowlistFromCommaString(): void
    {
        $bag = ConfigBag::fromArray(['nexus_state_allowlist' => 'mn, ca, ny']);
        self::assertSame(['CA', 'MN', 'NY'], $bag->nexusStateAllowlist);
    }

    public function testNexusStateAllowlistFromArray(): void
    {
        $bag = ConfigBag::fromArray(['nexus_state_allowlist' => ['mn', 'ca', 'NY']]);
        self::assertSame(['CA', 'MN', 'NY'], $bag->nexusStateAllowlist);
    }

    public function testNexusStateAllowlistDedupes(): void
    {
        $bag = ConfigBag::fromArray(['nexus_state_allowlist' => 'MN, mn, MN']);
        self::assertSame(['MN'], $bag->nexusStateAllowlist);
    }

    public function testNexusStateAllowlistRejectsInvalidTokens(): void
    {
        $bag = ConfigBag::fromArray(['nexus_state_allowlist' => 'MN, M1, USA, , 12']);
        self::assertSame(['MN'], $bag->nexusStateAllowlist);
    }

    public function testIsStateInNexusReturnsTrueWhenFilterDisabled(): void
    {
        $bag = ConfigBag::fromArray([
            'nexus_filter_enabled' => false,
            'nexus_state_allowlist' => 'MN',
        ]);
        self::assertTrue($bag->isStateInNexus('CA'));
        self::assertTrue($bag->isStateInNexus(null));
    }

    public function testIsStateInNexusReturnsTrueWhenAllowlistEmpty(): void
    {
        $bag = ConfigBag::fromArray([
            'nexus_filter_enabled' => true,
            'nexus_state_allowlist' => '',
        ]);
        self::assertTrue($bag->isStateInNexus('CA'));
    }

    public function testIsStateInNexusFiltersByAllowlist(): void
    {
        $bag = ConfigBag::fromArray([
            'nexus_filter_enabled' => true,
            'nexus_state_allowlist' => 'MN, CA',
        ]);
        self::assertTrue($bag->isStateInNexus('MN'));
        self::assertTrue($bag->isStateInNexus('mn'));
        self::assertTrue($bag->isStateInNexus(' CA '));
        self::assertFalse($bag->isStateInNexus('NY'));
    }

    public function testIsStateInNexusReturnsFalseForNullStateWithFilterOn(): void
    {
        $bag = ConfigBag::fromArray([
            'nexus_filter_enabled' => true,
            'nexus_state_allowlist' => 'MN',
        ]);
        self::assertFalse($bag->isStateInNexus(null));
    }
}
