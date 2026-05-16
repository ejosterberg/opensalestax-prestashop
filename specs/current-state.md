# Current state — opensalestax-prestashop

> Snapshot updated 2026-05-15. Refresh after each phase ships.

## Shipped

| Phase | Version | Ship date | Notes |
|---|---|---|---|
| 01 — Bootstrap + tax extension + SSRF defense | v0.1.0-alpha.1 | 2026-05-15 | First public tag. Composer-installable; PrestaShop ZIP build script; unit tests green; live-storefront integration test pending. |

## Code shape (post-Phase 01)

- **Framework-agnostic core** in `src/`, PSR-4 autoloaded under
  `OpenSalesTax\PrestaShop\` — `Support/` (ConfigBag,
  UrlValidator, ZipExtractor, CartPayloadBuilder,
  OpenSalesTaxClientFactory, RateCache, TaxCalculator, port
  interfaces) + `Exceptions/`.
- **PrestaShop 8.x glue** in `opensalestax.php` (the required
  module main file at the repo root) — declares the
  `OpenSalesTax` class extending `Module`, registers
  `actionTaxManagerFactory` hook, and exposes the admin
  settings form via `getContent()`.
- **SDK consumed via Composer** (`ejosterberg/opensalestax ^0.1`);
  bundled at build time into the `.zip` via
  `tools/build-zip.sh`.

## Quality bar (as of v0.1.0-alpha.1)

| Gate | Value |
|---|---|
| PHPUnit tests | TBD — recorded post-CI |
| PHPStan | level `max`, no errors |
| PHP-CS-Fixer | PSR-12 + risky, clean |
| `composer audit` | 0 vulnerabilities |
| Live-storefront integration test | Pending (captain follow-up) |

## Sibling-project map

| Connector | Distribution | License | Status |
|---|---|---|---|
| `opensalestax-opencart` | Packagist + .ocmod.zip | Apache-2.0 | v0.2.1 |
| `opensalestax-for-woocommerce` | Packagist + WordPress.org | Apache-2.0 OR GPL-2.0-or-later | v0.5.x |
| `opensalestax-magento` | Packagist (`ejosterberg/module-opensalestax`) | Apache-2.0 | shipping |
| `opensalestax-bagisto` | Packagist | Apache-2.0 | shipping |
| `opensalestax-vendure` | NPM | Apache-2.0 | shipping |
| `opensalestax-prestashop` | Packagist + .zip (Marketplace deferred) | Apache-2.0 OR GPL-2.0-or-later | v0.1.0-alpha.1 (this) |

## Open phases / planned work

| Phase | Slice |
|---|---|
| 02 — Operational polish | Cart-signature cache key; per-state nexus filter polish; admin "Test Connection" button; live-VM integration test |
| 03 — Surface + security | Per-jurisdiction tax-line surface; DNS-rebinding mitigation via cURL IP-pinning |
| 04 — Per-product tax-category mapping | Map PrestaShop tax rules group → OST category per product |
| 05 — Marketplace submission prep | Listing copy, screenshots, validator pass, Marketplace forms |

## Non-negotiables (recap — see `constitution.md` for full text)

- Apache-2.0 OR GPL-2.0-or-later + DCO sign-off + SPDX header on every source file
- No AI co-author trailers in commits
- US-shipping + USD-only — pass everything else through to PrestaShop
- Fail-soft default; merchant opts into fail-hard
- Never log PII or secrets
- Calculation-only disclaimer wherever tax is surfaced
