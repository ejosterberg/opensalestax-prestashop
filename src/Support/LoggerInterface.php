<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Support;

/**
 * Tiny PSR-3-shaped logger port.
 *
 * PrestaShop's `\PrestaShopLogger::addLog()` is not PSR-3 (its arguments are
 * positional and severity is an int). Rather than make the testable units
 * depend on PrestaShop's class hierarchy, we depend on this interface and
 * the module glue provides an adapter that forwards to PrestaShopLogger
 * with the appropriate severity mapping.
 *
 * Only `warning` and `info` are used by the connector — the rare error
 * case (e.g., misconfigured at fail-hard) becomes a thrown exception, not
 * a log.
 */
interface LoggerInterface
{
    /** @param array<string, scalar|null> $context */
    public function info(string $message, array $context = []): void;

    /** @param array<string, scalar|null> $context */
    public function warning(string $message, array $context = []): void;
}
