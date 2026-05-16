<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Tests\Stubs;

use OpenSalesTax\PrestaShop\Support\LoggerInterface;

/**
 * In-memory logger double for tests.
 *
 * Captures every call so tests can assert on log messages without depending
 * on PSR-3, Monolog, or PrestaShopLogger.
 */
final class ArrayLogger implements LoggerInterface
{
    /** @var array<int, array{level: string, message: string, context: array<string, scalar|null>}> */
    public array $records = [];

    public function info(string $message, array $context = []): void
    {
        $this->records[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function warning(string $message, array $context = []): void
    {
        $this->records[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    public function countAtLevel(string $level): int
    {
        $matches = array_filter($this->records, static fn (array $r): bool => $r['level'] === $level);
        return count($matches);
    }
}
