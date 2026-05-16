<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Tests\Unit\Support;

use OpenSalesTax\LineItem;
use OpenSalesTax\PrestaShop\Support\CartPayloadBuilder;
use PHPUnit\Framework\TestCase;

final class CartPayloadBuilderTest extends TestCase
{
    public function testHappyPathBuildsAddressAndLineItems(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total' => 100.00, 'cart_quantity' => 1]],
            ['country_iso' => 'US', 'postcode' => '55401'],
            'USD',
        );

        self::assertNotNull($result);
        [$address, $items, $signature] = $result;
        self::assertSame('55401', $address->zip5);
        self::assertCount(1, $items);
        self::assertSame('100.00', $items[0]->amount);
        self::assertSame('general', $items[0]->category);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $signature);
    }

    public function testNonUsCountryReturnsNull(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total' => 100.00]],
            ['country_iso' => 'CA', 'postcode' => 'K1A 0B1'],
            'USD',
        );
        self::assertNull($result);
    }

    public function testNonUsdCurrencyReturnsNull(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total' => 100.00]],
            ['country_iso' => 'US', 'postcode' => '55401'],
            'EUR',
        );
        self::assertNull($result);
    }

    public function testEmptyPostcodeReturnsNull(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total' => 100.00]],
            ['country_iso' => 'US', 'postcode' => ''],
            'USD',
        );
        self::assertNull($result);
    }

    public function testEmptyCartReturnsNull(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [],
            ['country_iso' => 'US', 'postcode' => '55401'],
            'USD',
        );
        self::assertNull($result);
    }

    public function testNegativeLineTotalIsSkipped(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [
                ['total' => -10.00],
                ['total' => 50.00],
            ],
            ['country_iso' => 'US', 'postcode' => '55401'],
            'USD',
        );
        self::assertNotNull($result);
        [, $items] = $result;
        self::assertCount(1, $items);
        self::assertSame('50.00', $items[0]->amount);
    }

    public function testNonNumericLineTotalIsSkipped(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [
                ['total' => 'abc'],
                ['total' => 25.00],
            ],
            ['country_iso' => 'US', 'postcode' => '55401'],
            'USD',
        );
        self::assertNotNull($result);
        [, $items] = $result;
        self::assertCount(1, $items);
    }

    public function testMissingTotalFallsBackToTotalPriceTaxExcl(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total_price_tax_excl' => 75.00]],
            ['country_iso' => 'US', 'postcode' => '55401'],
            'USD',
        );
        self::assertNotNull($result);
        [, $items] = $result;
        self::assertSame('75.00', $items[0]->amount);
    }

    public function testIsoCode2KeyAlsoAccepted(): void
    {
        // PrestaShop's Address object exposes country via id_country; the glue
        // resolves to either `country_iso` (preferred) or, for legacy /
        // alternative shapes, `iso_code_2`. Both must work.
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total' => 100.00]],
            ['iso_code_2' => 'US', 'postcode' => '55401'],
            'USD',
        );
        self::assertNotNull($result);
    }

    public function testCurrencyCaseInsensitive(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total' => 100.00]],
            ['country_iso' => 'US', 'postcode' => '55401'],
            'usd',
        );
        self::assertNotNull($result);
    }

    public function testCountryIsoCaseInsensitive(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total' => 100.00]],
            ['country_iso' => 'us', 'postcode' => '55401'],
            'USD',
        );
        self::assertNotNull($result);
    }

    public function testMalformedPostcodeReturnsNull(): void
    {
        $builder = new CartPayloadBuilder();
        $result = $builder->build(
            [['total' => 100.00]],
            ['country_iso' => 'US', 'postcode' => 'no-digits-here'],
            'USD',
        );
        self::assertNull($result);
    }

    public function testSignatureIsDeterministic(): void
    {
        $items = [
            new LineItem(amount: '50.00', category: 'general'),
            new LineItem(amount: '50.00', category: 'general'),
        ];
        $itemsReversed = array_reverse($items);
        self::assertSame(
            CartPayloadBuilder::signatureFor($items),
            CartPayloadBuilder::signatureFor($itemsReversed),
        );
    }

    public function testDifferentCartShapesHaveDifferentSignatures(): void
    {
        $cart1 = [new LineItem(amount: '100.00', category: 'general')];
        $cart2 = [
            new LineItem(amount: '50.00', category: 'general'),
            new LineItem(amount: '50.00', category: 'general'),
        ];
        self::assertNotSame(
            CartPayloadBuilder::signatureFor($cart1),
            CartPayloadBuilder::signatureFor($cart2),
        );
    }
}
