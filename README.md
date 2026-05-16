# OpenSalesTax for PrestaShop

> PrestaShop 8.x module that replaces PrestaShop's built-in tax
> calculation with a merchant-self-hosted **OpenSalesTax**
> engine for destination-based US sales tax.

[![CI](https://github.com/ejosterberg/opensalestax-prestashop/actions/workflows/ci.yml/badge.svg)](https://github.com/ejosterberg/opensalestax-prestashop/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-Apache--2.0%20OR%20GPL--2.0--or--later-blue.svg)](LICENSE)

> **Disclaimer.** Tax calculations are provided as-is for
> convenience. The merchant is solely responsible for
> tax-collection accuracy and remittance to the appropriate
> jurisdictions. Verify against your state Department of Revenue
> before remitting.

## Why

PrestaShop ships zone/state-based tax tables. They're a
reasonable starting point but they don't reflect the granular
city / county / special-district sales tax that US merchants
actually collect. This module hands the calculation off to your
own OpenSalesTax engine instance, which returns destination-
correct rates via the OST PHP SDK.

**This module calculates only.** It does NOT file or remit. The
merchant remains responsible for filing and payment.

## How it works

1. Module installs into PrestaShop's standard module loader.
2. Registers the `actionTaxManagerFactory` hook (the chosen
   extension point — see
   [`specs/decisions/001-taxmanager-hook.md`](specs/decisions/001-taxmanager-hook.md)).
3. On every cart-tax recalc, the hook builds an OST request
   from the cart's line totals + ship-to ZIP and asks the
   engine for tax.
4. Returns a `TaxManagerInterface` implementation that surfaces
   the engine's result as PrestaShop tax lines.

When the gates fail (non-US ship-to, non-USD currency,
unreachable engine in fail-soft mode), the hook returns null
and PrestaShop falls back to its built-in `GlobalTaxManager`.
Merchants don't need to keep both systems in sync — built-in is
the safety net.

## Install

### Via Composer (development / dev-installs)

```bash
composer require ejosterberg/opensalestax-prestashop
```

The module ends up in `vendor/ejosterberg/opensalestax-prestashop/`.
For PrestaShop's loader to find it, either symlink into
`modules/opensalestax/` or use the ZIP build below.

### Via PrestaShop module ZIP (merchants)

```bash
git clone https://github.com/ejosterberg/opensalestax-prestashop.git
cd opensalestax-prestashop
tools/build-zip.sh
# → dist/opensalestax-v0.1.0-alpha.1.zip
```

Then in PrestaShop admin:

**Modules → Module Manager → Upload a module → upload `opensalestax.zip`**

After install: **Modules → Module Manager → search "OpenSalesTax" → Configure**.

The PrestaShop Addons Marketplace path is deferred to v0.2+;
see [`specs/handoff.md`](specs/handoff.md).

## Configure

In **Modules → OpenSalesTax → Configure**:

| Setting | Default | Notes |
|---|---|---|
| **Enabled** | off | Turn ON after the URL + key are set. |
| **Engine base URL** | (empty) | Fully-qualified `https://...` URL of your OST engine. SSRF-validated; private nets rejected by default. |
| **API key** | (empty) | Bearer token if your engine requires auth. |
| **Timeout (seconds)** | 10 | Engine call timeout. |
| **Verify TLS certificates** | on | Disable only for self-signed dev engines. |
| **Allow private-network engines** | off | Set ON to permit `10.x.x.x` / `192.168.x.x` / `lan-engine.local` URLs. |
| **Fail hard on engine errors** | off | Default fail-soft falls back to PrestaShop's tax. ON blocks checkout when the engine is unreachable. |
| **Cache TTL (seconds)** | 3600 | Per-ZIP / per-cart-shape cache window. |
| **Enable nexus filter** | off | When ON, only ship-to addresses in the allowlist get destination-based tax. |
| **Nexus state allowlist** | (empty) | Comma-separated 2-letter state codes (`MN, CA, NY`). |

## Behavior

### What's calculated, what's not

| Cart shape | Behavior |
|---|---|
| US ship-to + USD + ZIP resolved + module enabled + nexus filter passes | OST engine call; per-line tax returned. |
| Non-US ship-to | Yield to PrestaShop. |
| Non-USD currency | Yield to PrestaShop. |
| ZIP can't be parsed (not 5 digits) | Yield to PrestaShop. |
| Module disabled / base URL empty | Yield to PrestaShop. |
| Nexus filter on, ship-to state not in allowlist | Yield to PrestaShop. |
| Engine call fails (timeout / 5xx / malformed) — fail-soft | Yield to PrestaShop. Logs structured warning. |
| Engine call fails — fail-hard | Throw; checkout blocked. |

### Caching

Engine responses cache per `(ZIP-5, cart-signature)` for the
configured TTL. The cart-signature is a deterministic 16-char
hex of the sorted `(category, amount)` tuples — so two carts
with the same ZIP but different mixes of products don't share
a stale cache entry.

### SSRF defense

The base URL is admin-controlled, which is the dominant attack
class for self-hosted-engine integrations. The validator
rejects RFC1918, loopback, link-local (incl. cloud metadata),
CGNAT, and multicast addresses by default. Merchants on a
private LAN opt in via the **Allow private-network engines**
toggle.

DNS-rebinding mitigation via cURL `RESOLVE` IP-pinning is on
the v0.2 roadmap (see
[`specs/handoff.md`](specs/handoff.md) — mirrors the OpenCart
sibling's v0.2.0 approach).

## Local development

```bash
composer install
composer check       # phpunit + phpstan + php-cs-fixer + composer audit
```

Granular runs:

```bash
composer test           # PHPUnit
composer stan           # PHPStan level max
composer cs             # PHP-CS-Fixer dry-run
composer cs-fix         # PHP-CS-Fixer apply fixes
composer audit          # CVE check
```

To package an installable PrestaShop ZIP:

```bash
tools/build-zip.sh
# → dist/opensalestax-vX.Y.Z.zip
```

## License

Dual-licensed under your choice of:

- **Apache License 2.0** — see [`LICENSE-APACHE.txt`](LICENSE-APACHE.txt)
- **GNU GPL v2 or later** — see [`LICENSE-GPL.txt`](LICENSE-GPL.txt)

SPDX expression: `Apache-2.0 OR GPL-2.0-or-later`. See
[`LICENSE`](LICENSE) for the dual-license declaration and
[`specs/decisions/002-license-choice.md`](specs/decisions/002-license-choice.md)
for the rationale.

PrestaShop core itself is OSL-3.0 + AFL-3.0; both are
GPL-compatible, so the combined work distributes cleanly under
the GPL-2.0-or-later option.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md). DCO sign-off
(`git commit -s`) is required on every commit.

## Sibling projects

| Connector | Repo |
|---|---|
| OpenCart 4.x | [opensalestax-opencart](https://github.com/ejosterberg/opensalestax-opencart) |
| WooCommerce | [opensalestax-for-woocommerce](https://github.com/ejosterberg/opensalestax-for-woocommerce) |
| Magento 2 | [opensalestax-magento](https://github.com/ejosterberg/opensalestax-magento) |
| Bagisto | [opensalestax-bagisto](https://github.com/ejosterberg/opensalestax-bagisto) |
| Vendure | [opensalestax-vendure](https://github.com/ejosterberg/opensalestax-vendure) |
| PHP SDK (used by all PHP-tier connectors) | [opensalestax](https://github.com/ejosterberg/opensalestax) |
