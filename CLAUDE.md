# CLAUDE.md — opensalestax-prestashop

> Project memory for Claude sessions on the PrestaShop
> connector. Read this AND `specs/constitution.md` +
> `specs/handoff.md` before writing code.

## Mission

Ship a free, self-hostable PrestaShop 8.x module that routes
PrestaShop's tax calculation through an OpenSalesTax engine for
destination-based US sales tax. Same value prop as the other
OST connectors.

## Stack

- **Language:** PHP 8.2+ (PrestaShop 8.x baseline)
- **Framework:** PrestaShop 8.x module loaded via the standard
  module loader (`opensalestax.php` + `config.xml`); registers
  the `actionTaxManagerFactory` hook (Decision 001 — locked
  2026-05-15).
- **Distribution:**
  - Composer / Packagist as `ejosterberg/opensalestax-prestashop`
    (dev installs).
  - Module ZIP via `tools/build-zip.sh` for direct upload (and
    the eventual Marketplace path; Marketplace submission
    deferred to v0.2+).
- **License:** Apache-2.0 OR GPL-2.0-or-later (Decision 002).
- **Tests:** PHPUnit 10 with anonymous-class stubs of
  PrestaShop's surface (no PrestaShop dependency in the test
  matrix).

## Architectural anchors

- **In-process module.** No standalone HTTP server, no
  webhooks, no inbound surface. The engine call is OUTBOUND
  only.
- **`actionTaxManagerFactory` hook** is the registered
  extension point. The hook returns a `TaxManagerInterface`
  implementation when the cart is eligible (US ship-to + USD +
  module enabled + URL valid + nexus filter passes); returns
  null otherwise so PrestaShop falls back to its default
  `GlobalTaxManager`.
- **USD-only / US-only**: non-USD orders or non-US ship-to
  addresses yield to PrestaShop's built-in tax flow
  (constitution §6).
- **Fail-soft default**: engine errors yield to PrestaShop +
  log a warning. Admin toggle opts into fail-hard (throw,
  blocks checkout).
- **Calculation only**: no filing, no remittance, no address
  validation (constitution §5).

## File layout

```
opensalestax-prestashop/
├── CLAUDE.md                  # this file
├── README.md
├── LICENSE                    # dual-license declaration
├── LICENSE-APACHE.txt
├── LICENSE-GPL.txt
├── CONTRIBUTING.md            # DCO sign-off mandatory
├── SECURITY.md
├── CHANGELOG.md
├── composer.json
├── phpunit.xml.dist
├── phpstan.neon
├── .php-cs-fixer.dist.php
├── opensalestax.php           # module main file (extends Module)
├── config.xml                 # PrestaShop module manifest
├── index.php                  # blank-redirect (PrestaShop convention)
├── specs/
│   ├── README.md
│   ├── constitution.md
│   ├── current-state.md
│   ├── handoff.md
│   ├── research/prestashop-tax.md
│   └── decisions/
│       ├── 001-taxmanager-hook.md
│       └── 002-license-choice.md
├── src/
│   ├── Exceptions/
│   │   ├── PrestaShopOpenSalesTaxException.php
│   │   └── ConfigurationException.php
│   └── Support/
│       ├── CacheRepositoryInterface.php
│       ├── CartPayloadBuilder.php
│       ├── ConfigBag.php
│       ├── LoggerInterface.php
│       ├── OpenSalesTaxClientFactory.php
│       ├── RateCache.php
│       ├── TaxCalculator.php
│       ├── UrlValidator.php
│       └── ZipExtractor.php
├── tests/
│   ├── Stubs/
│   │   ├── ArrayCache.php
│   │   └── ArrayLogger.php
│   └── Unit/Support/
│       ├── CartPayloadBuilderTest.php
│       ├── ConfigBagTest.php
│       ├── OpenSalesTaxClientFactoryTest.php
│       ├── RateCacheTest.php
│       ├── TaxCalculatorTest.php
│       ├── UrlValidatorTest.php
│       └── ZipExtractorTest.php
├── tools/
│   └── build-zip.sh
└── .github/
    └── workflows/
        └── ci.yml
```

## What NOT to do

- Don't ship a standalone HTTP server. The module loads in
  PrestaShop's process — Decision 001 locked it out.
- Don't add webhook subscriptions, JWT verification, or any
  inbound HTTP surface. The module makes OUTBOUND calls to the
  OST engine; it does not receive callbacks.
- Don't ship a copy of the OST engine — point at the merchant's
  instance via the admin URL.
- Don't add an SDK dependency on a private composer repository
  — keep every dep public on Packagist.
- Don't accept commits without DCO sign-off (`-s` flag).
- Don't ship admin UI features beyond the v0.1 minimum settings
  form (Phase 02 polishes the form; Phase 03 adds the
  per-jurisdiction surface).
- Don't override `TaxManagerFactory` via a core-class override
  (`override/classes/...`) — Decision 001 explicitly chose the
  hook approach for module-coexistence reasons.

## Releasing

- Semver tags `vX.Y.Z` on the single `main` branch (no
  branch-per-major).
- GitHub release on each tag.
- Composer / Packagist syncs automatically via webhook +
  Safe-API-token refresh (see captain policy).
- ZIP build via `tools/build-zip.sh` attached to GitHub release.
- PrestaShop Addons Marketplace submission deferred to Phase 05.

## Sibling-project map

See `specs/current-state.md` "Sibling-project map" section.
