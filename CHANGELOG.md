# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Pre-release identifiers (`-alpha.N`, `-rc.N`) signal that the listed version is not yet stable.

## [0.1.0-alpha.3] - 2026-05-19

### Added

- **CP-9 first-class shipping support.** The `TaxCalculator` now accepts
  an optional `?float $shippingCost` argument (the glue layer passes
  `Cart::getOrderShippingCost(null, false)`) and sends it to the
  OpenSalesTax engine as a top-level `shipping` field (engine v0.59.0+
  via the `shipping_first_class` capability flag, exposed in
  `ejosterberg/opensalestax` v0.3.0). The engine applies per-state
  shipping-taxability rules internally (MN "tax-if-items-taxable", MO/VA
  "separately-stated", MD "shipping-vs-handling"). The returned
  `CalculateResponse::$shipping->taxAmount` flows back to the caller via
  the existing response object.
- `Shipping` value object propagated through `CartPayloadBuilder`,
  `TaxCalculator`, and `RateCache` cache-key signature (so a cart with
  a different shipping cost gets a fresh cache key).

### Changed

- **Bumps `ejosterberg/opensalestax` constraint from `^0.2.0` to
  `^0.3.0`.** Picks up the new third arg on `Client::calculate(addr,
  lines, shipping?)` plus the `CalculateResponse::$shipping` and
  `$coverageWarning` response fields. Backward compatible — callers
  that don't pass `$shippingCost` behave identically to v0.1.0-alpha.2.

### Notes

- Out-of-nexus orders get 0 shipping tax (same gate as item tax).
- Engine v0.59.0+ required for shipping to be honored. Older engines
  silently ignore the field; the response's `shipping` field is `null`.

## [0.1.0-alpha.2] - 2026-05-19

### Changed

- **CP-8 Phase 5D: bumped `ejosterberg/opensalestax` constraint to `^0.2.0`.**
  Picks up the new `OpenSalesTax\Client::capabilities()` /
  `OpenSalesTax\Client::capabilitiesCached()` helpers for engine v0.59.0's
  `/v1/capabilities` endpoint. No merchant-visible behavior change in
  this release — the helper is available to connector code but not yet
  wired into any feature path. Constraint bump only; Test Connection
  surface enrichment deferred to v-next.

## [0.1.0-alpha.1] - 2026-05-15

### Added

- First public release of the PrestaShop 8.x connector.
- **Module main file** `opensalestax.php` declaring the `OpenSalesTax` class extending `Module`. Registers the `actionTaxManagerFactory` hook (the chosen extension point — see `specs/decisions/001-taxmanager-hook.md`) and exposes the admin settings form via `getContent()`.
- **PrestaShop module manifest** `config.xml` with the standard PS metadata.
- **Framework-agnostic core** under `src/` (PSR-4 namespace `OpenSalesTax\PrestaShop\`):
  - `Support\TaxCalculator` — top-level coordinator: inert/nexus/gate/client-factory chain → cache lookup → engine call → fail-soft/fail-hard error handling.
  - `Support\CartPayloadBuilder` — builds the OST `Address` + `LineItem[]` from PrestaShop's cart shape; gates US-only / USD-only / valid-ZIP / non-empty-cart.
  - `Support\OpenSalesTaxClientFactory` — builds the SDK client from a `ConfigBag`; runs the URL through `UrlValidator` for SSRF defense.
  - `Support\UrlValidator` — rejects RFC1918, loopback, link-local (incl. cloud metadata), CGNAT, and multicast hosts by default; opt-in for private-network engines.
  - `Support\RateCache` — per-`(ZIP-5, cart-signature)` cached engine responses; default 1h TTL.
  - `Support\ConfigBag` — frozen DTO of admin settings; defaults to disabled / fail-soft / TLS-on / private-nets-blocked / no-nexus-filter.
  - `Support\ZipExtractor` — normalizes free-text postcode into a 5-digit US ZIP.
  - `Support\CacheRepositoryInterface` + `Support\LoggerInterface` — tiny ports so the testable core doesn't depend on PrestaShop's `Cache` / `PrestaShopLogger` classes.
  - `Exceptions\PrestaShopOpenSalesTaxException` + `Exceptions\ConfigurationException`.
- **Per-state nexus filter** (mirrors WooCommerce v0.5.0 / Vendure v1.2 sibling pattern) — admin toggle + state allowlist; default off.
- **`composer.json`** — Composer-installable as `ejosterberg/opensalestax-prestashop`, type `prestashop-module`, PHP `>=8.2`, depends on `ejosterberg/opensalestax ^0.1`.
- **`tools/build-zip.sh`** — produces an installable PrestaShop module ZIP (`opensalestax.zip`) bundling the production-only Composer vendor tree.
- **CI** — GitHub Actions matrix on PHP 8.2 / 8.3 / 8.4; PHPUnit + PHPStan max + PHP-CS-Fixer + composer audit + DCO check.
- **Spec-kit** — `specs/constitution.md`, `specs/current-state.md`, `specs/handoff.md`, `specs/research/prestashop-tax.md`, `specs/decisions/001-taxmanager-hook.md`, `specs/decisions/002-license-choice.md`.
- **Top-level files** — `LICENSE` (dual-license declaration), `LICENSE-APACHE.txt`, `LICENSE-GPL.txt`, `CONTRIBUTING.md`, `SECURITY.md`, `README.md`, `CLAUDE.md`.

### Notes

- v0.1 ships the framework-agnostic core fully tested. The
  `actionTaxManagerFactory` hook returns null in v0.1 — we wire
  the concrete `TaxManagerInterface` adapter once we've
  validated the signature against a live PrestaShop install
  (live-storefront integration test is the recommended next
  slice; see `specs/handoff.md`).
- License is `Apache-2.0 OR GPL-2.0-or-later` — same as the
  WooCommerce sibling. PrestaShop core is OSL-3.0 + AFL-3.0
  (GPL-compatible); the dual expression keeps Composer
  consumers permissive while keeping the GPL door open for
  the eventual Marketplace path. See
  `specs/decisions/002-license-choice.md`.
