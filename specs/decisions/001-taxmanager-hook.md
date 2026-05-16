# ADR 001 — Use `actionTaxManagerFactory` hook (not core override)

> Status: **Accepted** 2026-05-15.
> Decider: captain (kickoff session).
> Related: `specs/research/prestashop-tax.md`.

## Context

PrestaShop 8.x exposes two viable extension points for replacing
the per-line tax calculation:

1. **`actionTaxManagerFactory` hook** — `TaxManagerFactory::getManager()`
   dispatches this hook before building its default
   `GlobalTaxManager`. Modules that return a
   `TaxManagerInterface` implementation from the hook take over
   tax computation for that cart line.
2. **Core class override** —
   `override/classes/tax/TaxManagerFactory.php` lets a module
   ship a subclass that PrestaShop's class loader uses in place
   of the core class.

Both achieve the same end result. They have different
operational properties.

## Decision

Use **Option 1: the `actionTaxManagerFactory` hook.**

The module ships a `TaxManagerOverride` class that implements
`TaxManagerInterface` and is returned from the hook callback in
`opensalestax.php`. The hook is registered during the module's
`install()` via the standard
`registerHook('actionTaxManagerFactory')` call.

## Rationale

1. **Module compatibility.** PrestaShop's core-override
   mechanism allows only ONE module to override any given class.
   If another tax-related module is already installed and has
   overridden `TaxManagerFactory`, our install will fail or
   silently conflict. The hook approach lets multiple modules
   coexist (the last to return a non-null result wins, which is
   a documented and predictable semantic).
2. **Upgrade safety.** Core overrides can break when the
   underlying core class signature changes between PrestaShop
   minor versions. The hook contract is far more stable.
3. **Marketplace acceptance.** PrestaShop Addons Marketplace
   prefers hook-based modules; core overrides trigger manual
   review and may delay approval.
4. **Testability.** The hook callback is a small function on our
   `OpenSalesTax` module class; we can mock the input shape
   easily. Core overrides require booting PrestaShop's class
   loader to verify, which is heavier.

## Trade-offs accepted

- **Last-write-wins ordering.** If a merchant installs two
  tax-replacement modules, only one will run for a given cart
  line. We document this in the README ("conflicts with other
  tax-replacement modules; install only one at a time").
- **Hook semantics evolution.** If PrestaShop ever changes the
  hook's return-value contract (e.g., requires a different
  interface), we'd need a release. Acceptable risk — the hook
  has been stable since PrestaShop 1.6.

## Implementation

In `opensalestax.php`:

```php
public function hookActionTaxManagerFactory(array $params)
{
    // $params: ['address' => Address, 'tax_rules_group_id' => int, 'type' => string]
    $address = $params['address'] ?? null;
    if (!$address instanceof Address) {
        return null;
    }
    return $this->buildTaxManagerOverride($address);
}
```

The `buildTaxManagerOverride()` factory wires the framework-
agnostic `TaxCalculator` from `src/Support/` and wraps it in a
PrestaShop-shaped `TaxManagerInterface`. If the calculator
returns null (US-only / USD-only gate failed, fail-soft), we
return null from the hook so PrestaShop falls back to its
default `GlobalTaxManager`.

## Out of scope for this ADR

- **Per-jurisdiction tax-line surface** (Phase 03) — that's a
  separate decision about how to surface the engine's
  jurisdiction breakdown in PrestaShop's totals UI.
- **Per-product tax-category mapping** (Phase 04) — keys on
  `id_tax_rules_group`; orthogonal to this ADR.
