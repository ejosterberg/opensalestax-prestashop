<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Support;

use OpenSalesTax\Client;
use OpenSalesTax\PrestaShop\Exceptions\PrestaShopOpenSalesTaxException;
use OpenSalesTax\Responses\CalculateResponse;
use OpenSalesTax\Shipping;
use Throwable;

/**
 * Top-level coordinator. The PrestaShop glue (`TaxManagerOverride` returned
 * from the `actionTaxManagerFactory` hook) constructs one of these per
 * cart-tax recalc and asks it to compute tax for the active cart.
 *
 * Pipeline:
 *  1. Inert-fast-path: if `ConfigBag::isActive()` is false, return null.
 *  2. Nexus filter: if the bag's nexus filter is on AND the destination
 *     state isn't in the allowlist, return null.
 *  3. Build payload (US + USD + valid-ZIP gate inside `CartPayloadBuilder`);
 *     if the builder returns null, return null.
 *  4. Build SDK client; if the factory returns null (URL rejected,
 *     fail-soft), return null.
 *  5. Cache lookup keyed on ZIP-5 + cart signature; on miss, call SDK
 *     `calculate()`.
 *  6. Engine error: fail-soft logs and returns null; fail-hard rethrows.
 *
 * Returns `CalculateResponse` on success, `null` on any "yield to
 * PrestaShop" outcome. Never returns a partial result.
 */
final class TaxCalculator
{
    public function __construct(
        private readonly ConfigBag $config,
        private readonly OpenSalesTaxClientFactory $clientFactory,
        private readonly CartPayloadBuilder $payloadBuilder,
        private readonly RateCache $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Read-only accessor for the underlying ConfigBag. Lets the PrestaShop
     * glue branch on settings (e.g. fail-hard disclosures, future
     * per-jurisdiction surface) without re-reading PrestaShop's
     * Configuration table.
     */
    public function getConfig(): ConfigBag
    {
        return $this->config;
    }

    /**
     * @param array<int, array<string, mixed>> $products       PrestaShop cart product array
     * @param array<string, mixed>             $shippingAddress Flat shape with `country_iso`,
     *                                                          `postcode`, optionally `state_iso`.
     * @param string                            $currency        Cart currency ISO code.
     * @param float|null                        $shippingCost   Optional pre-tax shipping cost
     *                                                          (typically `Cart::getOrderShippingCost(null, false)`).
     *                                                          When > 0, sent to the engine as a first-class
     *                                                          `shipping` field (engine v0.59.0+).
     */
    public function calculate(
        array $products,
        array $shippingAddress,
        string $currency,
        ?float $shippingCost = null,
    ): ?CalculateResponse {
        $prepared = $this->prepare($products, $shippingAddress, $currency, $shippingCost);
        if ($prepared === null) {
            return null;
        }
        [$client, $address, $lineItems, $signature, $shipping] = $prepared;

        try {
            return $this->cache->remember(
                $address->zip5,
                fn (): CalculateResponse => $this->callEngine($client, $address->zip5, $lineItems, $shipping),
                $signature,
            );
        } catch (Throwable $e) {
            return $this->handleEngineError($e, $address->zip5);
        }
    }

    /**
     * Run the inert / nexus / gate / client-factory chain.
     *
     * @param array<int, array<string, mixed>> $products
     * @param array<string, mixed>             $shippingAddress
     * @return array{0: Client, 1: \OpenSalesTax\Address, 2: \OpenSalesTax\LineItem[], 3: string, 4: Shipping|null}|null
     */
    private function prepare(
        array $products,
        array $shippingAddress,
        string $currency,
        ?float $shippingCost,
    ): ?array {
        if (!$this->config->isActive()) {
            return null;
        }

        $stateIsoRaw = $shippingAddress['state_iso'] ?? null;
        $stateIso    = is_string($stateIsoRaw) ? $stateIsoRaw : null;
        if (!$this->config->isStateInNexus($stateIso)) {
            return null;
        }

        $payload = $this->payloadBuilder->build($products, $shippingAddress, $currency, $shippingCost);
        $client  = $payload === null ? null : $this->clientFactory->make($this->config);
        if ($payload === null || $client === null) {
            return null;
        }
        [$address, $lineItems, $signature, $shipping] = $payload;
        return [$client, $address, $lineItems, $signature, $shipping];
    }

    /**
     * @param \OpenSalesTax\LineItem[] $lineItems
     */
    private function callEngine(Client $client, string $zip5, array $lineItems, ?Shipping $shipping): CalculateResponse
    {
        $start = microtime(true);
        $response = $client->calculate(new \OpenSalesTax\Address($zip5), $lineItems, $shipping);

        $this->logger->info('opensalestax: engine /v1/calculate ok', [
            'zip5'       => $zip5,
            'rtt_ms'     => (int) round((microtime(true) - $start) * 1000),
            'line_count' => count($response->lines),
            'shipping'   => $shipping !== null ? 1 : 0,
        ]);
        return $response;
    }

    /**
     * Fail-soft / fail-hard handler for engine errors raised inside the
     * cache resolver. Logs structured metadata in both modes; rethrows only
     * when fail-hard is on.
     */
    private function handleEngineError(Throwable $e, string $zip5): null
    {
        $this->logger->warning('opensalestax: engine call failed; applying fail-soft policy', [
            'zip5'      => $zip5,
            'fail_hard' => $this->config->failHard ? 1 : 0,
            'error'     => $e->getMessage(),
        ]);

        if ($this->config->failHard) {
            throw new PrestaShopOpenSalesTaxException(
                'OpenSalesTax engine unreachable; checkout blocked (fail-hard mode).',
                0,
                $e,
            );
        }
        return null;
    }
}
