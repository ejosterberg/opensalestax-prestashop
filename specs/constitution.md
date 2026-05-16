# Constitution — opensalestax-prestashop

> Non-negotiable principles for this connector. Inherits from
> the umbrella program constitution in
> `ejosterberg/open-sales-tax-integrations` (private).

## §1. Purpose

Ship a PrestaShop 8.x module that calculates US sales tax via a
merchant-self-hosted OpenSalesTax engine. Replace PrestaShop's
zone/state tax tables with destination-based real-time
calculation for US-shipping, USD-priced carts. Pass through all
other shapes.

## §2. License — Apache-2.0 OR GPL-2.0-or-later

Dual-licensed under Apache-2.0 OR GPL-2.0-or-later (recipient's
choice). Same expression as `opensalestax-for-woocommerce`.
PrestaShop core ships under OSL-3.0 + AFL-3.0; both are
GPL-compatible, and our combined-work distribution is the
GPL-2.0-or-later option when bundled with PrestaShop. The
Apache-2.0 option exists for downstream consumers who want a
permissive license, and to keep the OST portfolio's licensing
shape consistent.

DCO sign-off + SPDX headers mandatory on every file. SPDX
expression to use in headers:
`Apache-2.0 OR GPL-2.0-or-later`.

## §3. SDK-only path to the engine

The connector consumes the engine via `ejosterberg/opensalestax`
(PHP SDK, on Packagist). We never call engine HTTP endpoints
directly. If the SDK lacks something we need, file it upstream.

## §4. In-process module — no sidecar, no webhooks

The module loads in the merchant's PrestaShop process via the
standard PrestaShop module loader. We do not run a standalone
HTTP server. The OST engine call is OUTBOUND only — no inbound
webhook surface to secure.

## §5. Calculation only

No filing. No remittance. No address validation. The merchant
remits. Every user-facing tax line carries the calculation-only
disclaimer.

## §6. US-only, USD-only

The engine supports US destinations + USD line amounts only.
Outside those boundaries we yield to PrestaShop's built-in tax.
We do not pretend to handle multi-currency or non-US tax —
those are the merchant's problem.

## §7. Fail-soft default

If the engine is unreachable, malformed, or misconfigured, the
module falls back to PrestaShop's built-in tax and logs a
structured warning. The merchant opts into fail-hard via the
admin settings; the default is fail-soft because a
checkout-blocking error in production loses the merchant more
money than a brief tax-table fallback.

## §8. SSRF defense

The engine base URL is admin-controlled, which puts SSRF in the
threat model. The URL validator rejects RFC1918 / loopback /
link-local / CGNAT / multicast hosts by default. Merchants who
legitimately self-host on a private LAN opt in via the
"Allow private network engines" toggle.

## §9. Never log secrets or PII

The OST API key (if configured) flows in-memory only.
Structured logs carry numeric metadata (status, RTT, line
count) — never customer addresses, cart contents, or
credentials.

## §10. DCO required

Every commit `-s` signed. CI enforces. No AI co-author trailers.

## §11. Disclaimer everywhere user sees tax

> Tax calculations are provided as-is for convenience. The
> merchant is solely responsible for tax-collection accuracy and
> remittance to the appropriate jurisdictions. Verify against
> your state Department of Revenue before remitting.

This wording appears: README top, admin settings page, and
(when possible) the customer-facing checkout tax line tooltip
in v0.2.
