# ADR 002 — License: Apache-2.0 OR GPL-2.0-or-later (dual)

> Status: **Accepted** 2026-05-15.
> Decider: captain (kickoff session, per kickoff brief).
> Related: `LICENSE`, `LICENSE-APACHE.txt`, `LICENSE-GPL.txt`.

## Context

PrestaShop core itself is dual-licensed under
**OSL-3.0 + AFL-3.0**. The PrestaShop Addons Marketplace
suggests AFL-3.0 or OSL-3.0 for submitted modules but does NOT
hard-require it for self-distributed modules.

The OST connector portfolio uses different licenses across
ecosystems:

- `opensalestax` (PHP SDK) — Apache-2.0
- `opensalestax-opencart` — Apache-2.0
- `opensalestax-for-woocommerce` — Apache-2.0 OR GPL-2.0-or-later
  (because WordPress.org plugin directory requires GPL-2.0 or
  later or compatible)
- `opensalestax-Odoo` — LGPL-3.0-or-later OR AGPL-3.0-or-later
  (Odoo ecosystem convention)

## Decision

Adopt **`Apache-2.0 OR GPL-2.0-or-later`** for
`opensalestax-prestashop`, mirroring the WooCommerce sibling.

## Rationale

1. **GPL-compatible.** PrestaShop core is OSL-3.0 + AFL-3.0;
   both are GPL-compatible, so a combined work distributing the
   module alongside PrestaShop can be relicensed under the
   GPL-2.0-or-later option without conflict.
2. **Composer / dev consumers.** Downstream developers who want
   permissive integration (e.g., a managed-PrestaShop SaaS
   provider embedding the module in proprietary infrastructure)
   can elect Apache-2.0.
3. **Marketplace path open.** When we submit to PrestaShop
   Addons Marketplace (Phase 05), reviewers can elect the
   GPL-2.0-or-later option. If the Marketplace later
   hard-requires AFL-3.0 / OSL-3.0, we can add a third option to
   the SPDX expression without breaking existing licensees.
4. **Portfolio consistency.** Same pattern as WooCommerce keeps
   the cross-ecosystem story coherent (Eric's standing
   "WooCom not WC" preference for prose extends to keeping
   portfolio shape consistent in code conventions, too).
5. **OSI-approved.** Both options are OSI-approved licenses; no
   risk of validator rejection or downstream redistribution
   blockers.

## Trade-offs accepted

- **Two LICENSE files.** Maintain `LICENSE-APACHE.txt` and
  `LICENSE-GPL.txt` alongside the master `LICENSE` declaration
  file. Operationally trivial — the WooCommerce sibling does
  this.
- **No AFL-3.0 / OSL-3.0 today.** We're not on the Marketplace
  yet; if Phase 05 reveals a hard requirement, we add a third
  option. The current expression doesn't preclude that.

## Implementation

- `LICENSE` — top-level dual-license declaration document
  (mirrors WooCommerce sibling's text, adapted for PrestaShop).
- `LICENSE-APACHE.txt` — full Apache-2.0 text, copied from the
  WooCommerce sibling.
- `LICENSE-GPL.txt` — full GPL-2.0 text, copied from the
  WooCommerce sibling.
- Every PHP source file carries the SPDX header:
  `// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later`
- `composer.json` `"license"` field is the SPDX expression
  string (Composer accepts `"Apache-2.0 OR GPL-2.0-or-later"`
  as a valid expression).
- `opensalestax.php` (the PrestaShop main file) carries the
  same SPDX header in its top docblock.

## References

- PrestaShop developer docs on module licensing:
  <https://devdocs.prestashop-project.org/8/development/architecture/modules/>
- SPDX license-list reference:
  <https://spdx.org/licenses/>
- WooCommerce sibling's licensing decision (informed this ADR).
