# Research notes — PrestaShop tax extension surface

> Captured 2026-05-15 while bootstrapping
> `opensalestax-prestashop`. These notes inform
> `specs/decisions/001-taxmanager-hook.md`.

## PrestaShop's tax architecture (8.x)

PrestaShop computes tax through the `TaxManager` family of
classes (`classes/tax/TaxManagerFactory.php`,
`classes/tax/TaxConfiguration.php`,
`classes/tax/TaxCalculator.php`):

1. The cart total computation walks each `OrderLine` /
   `Cart::getProducts()` row.
2. For each row, the framework asks `TaxManagerFactory::getManager($address, $taxRulesGroupId)`
   for a `TaxManagerInterface`.
3. The factory looks up tax rules in the merchant's
   `tax_rules_group` table based on the address (zone, country,
   state, ZIP) and returns a `TaxManagerInterface`
   implementation (default: `GlobalTaxManager`).
4. The framework then calls
   `$manager->getTaxCalculator()->getTotalRate()` (or
   `addTaxes($price)`) to compute the per-line tax.

## Two viable extension points

### Option A — `actionTaxManagerFactory` hook (chosen)

`TaxManagerFactory::getManager()` dispatches the
`actionTaxManagerFactory` hook BEFORE building its own
`GlobalTaxManager`. If a module returns a `TaxManagerInterface`
implementation from the hook, the factory uses it instead.

**Pros:**
- Standard, well-supported PrestaShop hook pattern.
- Doesn't require overriding core classes (which can break
  module compatibility — only one module can override a class).
- Module compatibility flag `actionTaxManagerFactory` is
  registered via the standard `registerHook()` API.

**Cons:**
- Hook must return a `TaxManagerInterface` implementation,
  which means we ship our own concrete `OstaxTaxManager` class
  satisfying the interface.
- If multiple modules subscribe, only the last one to return a
  non-null wins (PrestaShop's hook semantics).

### Option B — `Override\classes\tax\TaxManagerFactory` core override

PrestaShop allows shipping `override/classes/...` files that
swap in custom subclasses at boot. We would override
`TaxManagerFactory::getManager()` directly.

**Pros:**
- Total control over the dispatch path.

**Cons:**
- **Only one module can override a given class** — so installing
  alongside any other tax-module that uses the same override
  breaks both. This is a known PrestaShop foot-gun.
- Updates to PrestaShop core that touch the same class are a
  manual merge.

**Decision:** Option A. See
`specs/decisions/001-taxmanager-hook.md` for the full rationale.

## `Cart::getProducts()` shape (verified against PrestaShop 8.1
core source, `classes/Cart.php`)

Each entry is an associative array with at least:

- `id_product` — int
- `id_product_attribute` — int
- `cart_quantity` — int
- `quantity` — alias for `cart_quantity`
- `name` — string
- `price` — float (unit price, ex-tax)
- `price_wt` — float (unit price, w/ tax)
- `total` — float (line total ex-tax = price * cart_quantity)
- `total_wt` — float (line total w/ tax)
- `id_tax_rules_group` — int (the product's tax rules group ID)

For the OST engine payload we need `total` (ex-tax line total)
and `cart_quantity`. The category mapping (Phase 04) will key
on `id_tax_rules_group`.

## `Address` shape

PrestaShop's `Address` object has:

- `id` — int
- `id_country` — int → resolve to ISO code via
  `Country::getIsoById($address->id_country)` returning `'US'`,
  `'CA'`, etc.
- `id_state` — int → resolve to ISO code via
  `State::getIsoById($address->id_state)` returning `'MN'`,
  `'CA'`, etc.
- `postcode` — string (free-text from checkout form)

Our gate flow:
1. ISO country code === 'US'?  No → return null.
2. ISO currency code === 'USD'? No → return null.
3. Postcode matches `^\d{5}` (zip-extractor)? No → return null.
4. Cart has at least one line with positive amount? No →
   return null.

## Currency lookup

`Cart::id_currency` is an int. Resolve via
`Currency::getIsoCodeById($cart->id_currency)` which returns
the 3-letter ISO code (`'USD'`, `'EUR'`, etc.).

## License compatibility — PrestaShop module guidelines

PrestaShop's official module guidelines suggest AFL-3.0 or
OSL-3.0 for marketplace submissions, but neither is mandatory
for self-distributed modules. The validator (`validator.prestashop.com`)
checks for an SPDX header in the main module file but accepts
any OSI-approved license expression. Our
`Apache-2.0 OR GPL-2.0-or-later` is GPL-compatible and
OSI-approved; the recipient may elect the GPL-2.0-or-later
option to satisfy any redistribution requirements.

The Marketplace submission process (Phase 05) will surface any
hard requirements. At that point we can either:
- Add AFL-3.0 / OSL-3.0 as a third option in the SPDX
  expression, or
- Submit under GPL-2.0-or-later (already in the dual-license).

Both paths preserve the existing licensing for downstream
Composer consumers.

## Cache surface

PrestaShop ships `Cache::getInstance()` returning a
`Cache_Memcached` / `Cache_Apc` / `Cache_Xcache` /
`Cache_File` (depending on `parameters.php`). All implement
`get($key)`, `set($key, $value, $ttl)`, `delete($key)`.

We keep a thin `CacheRepositoryInterface` port (mirrors the
OpenCart pattern) and provide a PrestaShop-specific adapter at
boot. Tests inject an in-memory double.

## Logger surface

PrestaShop ships `PrestaShopLogger::addLog($message, $severity, $errorCode, $objectType, $objectId, $allowDuplicate)`
writing to the `ps_log` table. Severities: 1 (info), 2
(warning), 3 (error), 4 (major).

We keep our own PSR-3-shaped `LoggerInterface` port and adapt to
`PrestaShopLogger` at boot. Severity mapping: `info` → 1,
`warning` → 2.

## Hook list to register

Module's `install()` calls:

- `actionTaxManagerFactory` — the primary extension point.

That's it for v0.1. Future phases may add:

- `displayBeforeCarrier` / `displayShoppingCartFooter` for
  the per-jurisdiction surface (Phase 03).
- `actionAdminControllerSetMedia` for any admin-side JS in
  Phase 02.

## References

- PrestaShop core source: <https://github.com/PrestaShop/PrestaShop>
  - `classes/tax/TaxManagerFactory.php`
  - `classes/tax/TaxManagerInterface.php`
  - `classes/tax/TaxCalculator.php`
- PrestaShop developer docs:
  <https://devdocs.prestashop-project.org/8/modules/>
- PrestaShop module licensing FAQ:
  <https://devdocs.prestashop-project.org/8/development/architecture/modules/>
