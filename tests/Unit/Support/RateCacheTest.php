<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Tests\Unit\Support;

use OpenSalesTax\PrestaShop\Support\RateCache;
use OpenSalesTax\PrestaShop\Tests\Stubs\ArrayCache;
use OpenSalesTax\Responses\CalculateResponse;
use PHPUnit\Framework\TestCase;

final class RateCacheTest extends TestCase
{
    public function testKeyForBareZip(): void
    {
        self::assertSame('ostax:rate:55401', RateCache::keyFor('55401'));
        self::assertSame('ostax:rate:55401', RateCache::keyFor('55401', null));
        self::assertSame('ostax:rate:55401', RateCache::keyFor('55401', ''));
    }

    public function testKeyForZipPlusSignature(): void
    {
        self::assertSame(
            'ostax:rate:55401:abcdef0123456789',
            RateCache::keyFor('55401', 'abcdef0123456789'),
        );
    }

    public function testRememberMissCallsResolverAndStores(): void
    {
        $store = new ArrayCache();
        $cache = new RateCache($store, 60);

        $resolved = $this->fakeResponse();
        $count = 0;
        $result = $cache->remember('55401', function () use ($resolved, &$count) {
            $count++;
            return $resolved;
        }, 'sig123');

        self::assertSame(1, $count);
        self::assertSame('8.83', $result->taxTotal);
        self::assertSame(1, $store->setCount);
        self::assertSame(60, $store->lastTtl);
        self::assertArrayHasKey('ostax:rate:55401:sig123', $store->store);
    }

    public function testRememberHitSkipsResolver(): void
    {
        $store = new ArrayCache();
        $cache = new RateCache($store, 60);

        $first = $this->fakeResponse();
        $count = 0;
        $cache->remember('55401', function () use ($first, &$count) {
            $count++;
            return $first;
        }, 'sig123');

        $second = $cache->remember('55401', function () use (&$count) {
            $count++;
            self::fail('resolver called on cache hit');
        }, 'sig123');

        self::assertSame(1, $count);
        self::assertSame($first->taxTotal, $second->taxTotal);
    }

    public function testDifferentSignaturesDoNotShareCacheEntry(): void
    {
        $store = new ArrayCache();
        $cache = new RateCache($store, 60);
        $resolved = $this->fakeResponse();

        $cache->remember('55401', fn () => $resolved, 'sigA');
        $cache->remember('55401', fn () => $resolved, 'sigB');

        self::assertSame(2, $store->setCount);
        self::assertCount(2, $store->store);
    }

    private function fakeResponse(): CalculateResponse
    {
        return CalculateResponse::fromArray([
            'subtotal'   => '100.00',
            'tax_total'  => '8.83',
            'lines'      => [
                [
                    'amount'        => '100.00',
                    'category'      => 'general',
                    'tax'           => '8.83',
                    'rate_pct'      => '8.83',
                    'jurisdictions' => [
                        ['name' => 'Minnesota', 'type' => 'state', 'rate_pct' => '6.875', 'tax' => '6.88'],
                        ['name' => 'Hennepin',  'type' => 'county', 'rate_pct' => '1.955', 'tax' => '1.95'],
                    ],
                ],
            ],
            'disclaimer' => 'calc-only',
        ]);
    }
}
