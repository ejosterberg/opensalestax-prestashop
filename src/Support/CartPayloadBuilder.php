<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Support;

use OpenSalesTax\Address;
use OpenSalesTax\Exceptions\OpenSalesTaxValidationException;
use OpenSalesTax\LineItem;
use OpenSalesTax\Shipping;

/**
 * Build SDK `Address` + `LineItem[]` from a PrestaShop cart shape.
 *
 * Inputs:
 *  - $products: the array returned by `Cart::getProducts()` — each entry has
 *               at least `total` (line total ex-tax, decimal),
 *               `cart_quantity`, `id_tax_rules_group`, `name`.
 *  - $shippingAddress: a flat array shape we project from the
 *               PrestaShop `Address` object — `country_iso` (uppercase ISO,
 *               e.g. 'US') and `postcode`. Optionally `state_iso` for the
 *               nexus filter (Phase 02+). The module glue resolves these
 *               from `Country::getIsoById()` / `State::getIsoById()` before
 *               handing the array to the builder.
 *  - $currency: the cart's currency code (USD required), resolved by the
 *               glue via `Currency::getIsoCodeById()`.
 *
 * Output: a tuple `[Address, LineItem[], cartSignature]` ready to hand to
 * the SDK, OR null if the gate fails (non-US country / non-USD currency /
 * missing ZIP / empty cart). The `TaxCalculator` translates null into
 * "yield to PrestaShop".
 */
final class CartPayloadBuilder
{
    private const COUNTRY_US = 'US';
    private const CURRENCY_USD = 'USD';

    public function __construct(
        private readonly ZipExtractor $zipExtractor = new ZipExtractor(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<string, mixed> $shippingAddress
     * @param string $currency
     * @param float|null $shippingCost Pre-tax shipping cost in cart currency,
     *     typically `Cart::getOrderShippingCost(null, false)`. When > 0, the
     *     returned tuple includes a typed Shipping value object so the engine
     *     applies first-class shipping-tax rules (engine v0.59.0+). CP-9.
     *
     * @return array{0: Address, 1: LineItem[], 2: string, 3: Shipping|null}|null Tuple of
     *     [Address, LineItem[], cartSignature, shipping]. The signature is a stable
     *     16-hex-char prefix of SHA-256 over the sorted `(category, amount)`
     *     tuples — used by `RateCache` to keep mixed-category carts at the
     *     same ZIP from colliding on a stale cached response.
     */
    public function build(
        array $products,
        array $shippingAddress,
        string $currency,
        ?float $shippingCost = null,
    ): ?array {
        $zip5 = $this->extractEligibleZip($shippingAddress, $currency);
        if ($zip5 === null) {
            return null;
        }
        $lineItems = $this->buildLineItems($products);
        if ($lineItems === []) {
            return null;
        }
        return $this->safeAddressTuple($zip5, $lineItems, $this->buildShipping($shippingCost));
    }

    /**
     * Build a Shipping value-object from the raw cart shipping cost. Returns
     * null when the cost is missing, zero, or non-positive — the engine
     * treats absent shipping as "no shipping line".
     */
    private function buildShipping(?float $shippingCost): ?Shipping
    {
        if ($shippingCost === null || $shippingCost <= 0.0) {
            return null;
        }
        try {
            return new Shipping(
                amount: number_format($shippingCost, 2, '.', ''),
                separatelyStated: true,
            );
        } catch (OpenSalesTaxValidationException) {
            return null;
        }
    }

    /**
     * Compute the cart signature for an arbitrary line-item list (+ optional shipping).
     *
     * Deterministic: same `(category, amount)` set → same digest, regardless
     * of order. Different categories OR different amounts → different
     * digest. Shipping contributes a `ship:amount` token so a cart with
     * a different shipping cost gets a fresh cache key.
     *
     * 16 hex chars (8 bytes) is enough collision resistance for a per-ZIP
     * cache.
     *
     * @param LineItem[] $lineItems
     */
    public static function signatureFor(array $lineItems, ?Shipping $shipping = null): string
    {
        $tuples = [];
        foreach ($lineItems as $item) {
            $tuples[] = $item->category . ':' . $item->amount;
        }
        sort($tuples);
        if ($shipping !== null) {
            $tuples[] = 'ship:' . $shipping->amount;
        }
        return substr(hash('sha256', implode('|', $tuples)), 0, 16);
    }

    /**
     * Build the SDK Address and pair it with the prepared line items + optional shipping.
     *
     * @param LineItem[] $lineItems
     * @return array{0: Address, 1: LineItem[], 2: string, 3: Shipping|null}|null
     */
    private function safeAddressTuple(string $zip5, array $lineItems, ?Shipping $shipping): ?array
    {
        try {
            return [new Address($zip5), $lineItems, self::signatureFor($lineItems, $shipping), $shipping];
        } catch (OpenSalesTaxValidationException) {
            return null;
        }
    }

    /**
     * Returns the 5-digit ZIP iff currency, country, and postcode all pass
     * the gate; null otherwise.
     *
     * @param array<string, mixed> $shippingAddress
     */
    private function extractEligibleZip(array $shippingAddress, string $currency): ?string
    {
        $currencyOk = strtoupper($currency) === self::CURRENCY_USD;
        $countryRaw = $shippingAddress['country_iso'] ?? $shippingAddress['iso_code_2'] ?? '';
        $countryOk  = is_string($countryRaw)
            && strtoupper(trim($countryRaw)) === self::COUNTRY_US;
        $rawPostcode = $shippingAddress['postcode'] ?? '';
        $postcodeOk  = is_string($rawPostcode);

        if (!$currencyOk || !$countryOk || !$postcodeOk) {
            return null;
        }
        return $this->zipExtractor->extract($rawPostcode);
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return LineItem[]
     */
    private function buildLineItems(array $products): array
    {
        $items = [];
        foreach ($products as $product) {
            $amount = $this->lineAmount($product);
            if ($amount === null) {
                continue;
            }
            try {
                $items[] = new LineItem(amount: $amount, category: 'general');
            } catch (OpenSalesTaxValidationException) {
                continue;
            }
        }
        return $items;
    }

    /**
     * Coerce a PrestaShop product entry's line total into a decimal string the
     * SDK accepts.
     *
     * PrestaShop's `$product['total']` is the line total ex-tax (price *
     * cart_quantity), already decimal. We accept int/float/string-numeric
     * and convert via `number_format` to a fixed 2-decimal-place string
     * with no thousands separator.
     *
     * Falls back to `total_wt` minus per-line tax only if `total` is
     * missing — but in practice PrestaShop always populates both. We don't
     * try to back-derive from `price_wt * quantity` because that path is
     * lossy at high-precision rounding boundaries.
     *
     * @param array<string, mixed> $product
     */
    private function lineAmount(array $product): ?string
    {
        $raw = $product['total'] ?? $product['total_price_tax_excl'] ?? null;
        if ($raw === null || !is_numeric($raw)) {
            return null;
        }
        $float = (float) $raw;
        return $float < 0.0 ? null : number_format($float, 2, '.', '');
    }
}
