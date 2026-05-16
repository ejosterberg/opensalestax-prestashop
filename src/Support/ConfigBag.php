<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\PrestaShop\Support;

/**
 * Frozen DTO of the connector's admin-panel settings.
 *
 * Built once per request by the module glue layer from PrestaShop's
 * `Configuration` table (or a default array in tests). All access is
 * read-only — the glue layer passes this around instead of letting
 * consumers reach back into PrestaShop's configuration service.
 *
 * Defaults mirror the documented "safe" install: disabled, fail-soft,
 * TLS-on, private nets blocked, no nexus filter (calculate everywhere
 * the gates allow).
 */
final readonly class ConfigBag
{
    /**
     * @param string[] $nexusStateAllowlist Sorted unique uppercase 2-letter US
     *     state ISO codes ("MN", "CA"). When non-empty AND
     *     `nexusFilterEnabled` is true, the connector only calculates tax for
     *     ship-to addresses whose resolved state is in this list. When empty
     *     or the filter is off, all US addresses are eligible. Default: empty
     *     (calculate everywhere).
     */
    public function __construct(
        public bool $enabled,
        public string $baseUrl,
        public string $apiKey,
        public float $timeoutSeconds,
        public bool $tlsVerify,
        public bool $allowPrivateNets,
        public bool $failHard,
        public int $cacheTtlSeconds,
        public bool $nexusFilterEnabled = false,
        public array $nexusStateAllowlist = [],
    ) {
    }

    /**
     * Build from PrestaShop's Configuration array (or any flat string map).
     *
     * Recognized keys (the module glue prefixes them with `OSTAX_` when
     * reading from the `ps_configuration` table, but accepted bare here so
     * the unit tests don't need to mirror that prefix):
     *
     * @param array<string, mixed> $settings
     */
    public static function fromArray(array $settings): self
    {
        return new self(
            enabled: self::boolish($settings, 'enabled', false),
            baseUrl: self::stringish($settings, 'base_url', ''),
            apiKey: self::stringish($settings, 'api_key', ''),
            timeoutSeconds: self::floatish($settings, 'timeout_seconds', 10.0),
            tlsVerify: self::boolish($settings, 'tls_verify', true),
            allowPrivateNets: self::boolish($settings, 'allow_private_nets', false),
            failHard: self::boolish($settings, 'fail_hard', false),
            cacheTtlSeconds: self::intish($settings, 'cache_ttl_seconds', 3600),
            nexusFilterEnabled: self::boolish($settings, 'nexus_filter_enabled', false),
            nexusStateAllowlist: self::stateList($settings, 'nexus_state_allowlist'),
        );
    }

    /**
     * True when the module is ON and minimally configured (base_url set).
     * The glue layer uses this as a fast-path: if false, return control to
     * PrestaShop's tax flow without further work.
     */
    public function isActive(): bool
    {
        return $this->enabled && $this->baseUrl !== '';
    }

    /**
     * Returns true iff the given state ISO code is permitted by the active
     * nexus filter.
     *
     * - When `nexusFilterEnabled` is false → always true (filter disabled).
     * - When the allowlist is empty → always true (no states means
     *   "calculate everywhere"; the toggle without states is a no-op rather
     *   than a footgun that suppresses all tax).
     * - When `$stateIso` is null (we couldn't resolve a state) → false in
     *   filter-on mode; true otherwise. We can't safely apply nexus when the
     *   destination state is unknown.
     */
    public function isStateInNexus(?string $stateIso): bool
    {
        if (!$this->nexusFilterEnabled || $this->nexusStateAllowlist === []) {
            return true;
        }
        if ($stateIso === null) {
            return false;
        }
        return in_array(strtoupper(trim($stateIso)), $this->nexusStateAllowlist, true);
    }

    /** @param array<string, mixed> $bag */
    private static function boolish(array $bag, string $key, bool $default): bool
    {
        if (!isset($bag[$key])) {
            return $default;
        }
        $raw = $bag[$key];
        return match (true) {
            is_bool($raw)   => $raw,
            is_int($raw)    => $raw !== 0,
            is_string($raw) => in_array(strtolower(trim($raw)), ['1', 'true', 'on', 'yes'], true),
            default         => $default,
        };
    }

    /** @param array<string, mixed> $bag */
    private static function stringish(array $bag, string $key, string $default): string
    {
        $raw = $bag[$key] ?? null;
        return is_string($raw) ? trim($raw) : $default;
    }

    /** @param array<string, mixed> $bag */
    private static function floatish(array $bag, string $key, float $default): float
    {
        $raw = $bag[$key] ?? null;
        if (is_int($raw) || is_float($raw) || (is_string($raw) && is_numeric($raw))) {
            return (float) $raw;
        }
        return $default;
    }

    /** @param array<string, mixed> $bag */
    private static function intish(array $bag, string $key, int $default): int
    {
        $raw = $bag[$key] ?? null;
        if (is_int($raw) || is_float($raw) || (is_string($raw) && is_numeric($raw))) {
            return (int) $raw;
        }
        return $default;
    }

    /**
     * Parse a comma-separated string of US state ISO codes into a deduped,
     * sorted, uppercased `string[]`. Accepts string ("MN, CA, NY"), array
     * (["MN", "CA"]), or any value type — anything non-coercible drops out.
     *
     * Filters to alphabetic 2-character tokens; "MN" passes, "M1" doesn't,
     * "MNN" doesn't. We do not validate against the actual 50-state list
     * here because the connector is data-driven; if Eric ever ships a
     * territory (PR, GU), the merchant just adds the code to the allowlist.
     *
     * @param array<string, mixed> $bag
     * @return string[]
     */
    private static function stateList(array $bag, string $key): array
    {
        $raw = $bag[$key] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            $candidates = $raw;
        } elseif (is_string($raw)) {
            $candidates = explode(',', $raw);
        } else {
            return [];
        }

        $codes = [];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $trimmed = strtoupper(trim($candidate));
            if (preg_match('/^[A-Z]{2}$/', $trimmed) !== 1) {
                continue;
            }
            $codes[] = $trimmed;
        }
        $codes = array_values(array_unique($codes));
        sort($codes);
        return $codes;
    }
}
