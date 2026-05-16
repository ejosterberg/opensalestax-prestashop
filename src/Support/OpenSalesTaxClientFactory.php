<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Support;

use GuzzleHttp\Client as GuzzleClient;
use InvalidArgumentException;
use OpenSalesTax\Client;
use OpenSalesTax\PrestaShop\Exceptions\ConfigurationException;

/**
 * Build a configured SDK `OpenSalesTax\Client` from a `ConfigBag`.
 *
 * Returns `null` when:
 *  - the bag's `baseUrl` is empty (module is inert; the caller yields to
 *    PrestaShop)
 *  - URL validation fails AND `failHard` is false (we log + return null;
 *    the fail-hard path rethrows as `ConfigurationException`)
 *
 * Returns a built `Client` otherwise, with TLS verify driven by the
 * `tlsVerify` flag and timeout pulled from `timeoutSeconds`.
 */
class OpenSalesTaxClientFactory
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?UrlValidator $validator = null,
    ) {
    }

    public function make(ConfigBag $config): ?Client
    {
        if ($config->baseUrl === '') {
            return null;
        }

        $validator = $this->validator ?? new UrlValidator($config->allowPrivateNets);

        try {
            $validator->validateAndResolve($config->baseUrl);
        } catch (InvalidArgumentException $e) {
            $this->logger->warning('opensalestax: base URL rejected by validator', [
                'reason' => $e->getMessage(),
            ]);
            if ($config->failHard) {
                throw new ConfigurationException($e->getMessage(), 0, $e);
            }
            return null;
        }

        $guzzle = new GuzzleClient($this->buildHttpOptions($config));

        return new Client(
            baseUrl: $config->baseUrl,
            apiKey: $config->apiKey !== '' ? $config->apiKey : null,
            timeoutSeconds: $config->timeoutSeconds,
            httpClient: $guzzle,
        );
    }

    /**
     * Build the Guzzle config array.
     *
     * v0.1 wires only timeout + TLS verify. cURL `RESOLVE` IP-pinning
     * (DNS-rebinding defense, mirrors OpenCart v0.2.0) is deferred to
     * Phase 03 — same code structure, additive change.
     *
     * @return array<string, mixed>
     */
    private function buildHttpOptions(ConfigBag $config): array
    {
        return [
            'timeout' => $config->timeoutSeconds,
            'verify'  => $config->tlsVerify,
        ];
    }
}
