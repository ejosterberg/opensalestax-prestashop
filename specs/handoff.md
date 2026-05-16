# Handoff — opensalestax-prestashop

> What the next Claude session should pick up first. Refresh at the end of every session.

## Pick up here

**v0.1.0-alpha.1 shipped 2026-05-15.** First public release.
Repo + Composer package + initial test coverage in place. CI is
green. Tag pushed. GitHub release published.

**Recommended next slice — pick ONE of:**

- **Live-storefront integration test** (orthogonal, can land
  alongside any phase). Provision a Debian 13 VM in 900-999 per
  `~/.claude/proxmox-playbook.md`; install PrestaShop 8.x +
  MariaDB; install the module via the built ZIP; configure the
  engine URL to the captain's live OST engine
  (`http://10.32.161.X:port` once allocated); place a US/55401
  cart and verify destination-based tax appears. Document under
  `docs/INTEGRATION-CHECK.md`.
- **Phase 02 — Operational polish**: cart-signature cache key
  (mirrors OpenCart v0.1.1), admin "Test Connection" button,
  customer-group exemption, nexus-filter polish. Pattern is
  already well-understood from the OpenCart sibling — port the
  approach.
- **Phase 03 — Surface + security**: per-jurisdiction tax-line
  surface (one PrestaShop tax row per state/county/city), DNS
  rebinding mitigation via cURL `CURLOPT_RESOLVE` IP-pinning
  (mirrors OpenCart v0.2.0). Larger lift; bigger user-visible
  change.

**Recommend:** ship the live-storefront integration test first.
v0.1.0-alpha.1 is unverified against a real PrestaShop install;
catching surface mismatches now is cheaper than after Phase 02
adds more code.

## Captain follow-ups

- **Packagist new-package submit.** The Safe API token can refresh
  existing packages but cannot create new ones. Eric needs to do
  the 2-min web action at
  `https://packagist.org/packages/submit` with URL
  `https://github.com/ejosterberg/opensalestax-prestashop`. Same
  pattern as the WooCom v0.6.0 rename.
- **PrestaShop Addons Marketplace developer account.** Out of
  scope for v0.1; needed before any Phase 05 work.
- **Live OST engine endpoint** for the integration-test VM.

## Risks / things to watch (next phase, whichever it is)

- **`actionTaxManagerFactory` hook timing.** Verify against a
  live PrestaShop install that the hook fires before the cart
  total is computed (and not after, which would mean our
  override never lands). Research notes in
  `specs/research/prestashop-tax.md` say it fires inside
  `TaxManagerFactory::getManager()` which is called per-product
  on every cart total recalc — should be fine, but verify.
- **PrestaShop's `Cart` API.** Our `CartPayloadBuilder` test
  uses anonymous-class stubs. The real `Cart::getProducts()`
  returns a list of arrays with `total_wt`, `cart_quantity`,
  etc. Verify the field names against a real cart dump.
- **Admin form CSRF.** PrestaShop's `HelperForm` handles CSRF
  on submission, but our `getContent()` body must use the
  framework's `Tools::isSubmit()` check, not a hand-rolled
  POST handler. Standard pattern; flagged as a watch-out.
- **Multi-currency.** PrestaShop supports per-cart currency.
  Our gate keys on `Cart::id_currency` resolved to the ISO code
  via `Currency::getIsoCodeById()` — verify in the
  integration test.

## Last verified

- 2026-05-15 — v0.1.0-alpha.1 cut. PHPUnit + PHPStan max +
  PHP-CS-Fixer + composer audit all green in CI. Tag `v0.1.0-alpha.1`
  pushed to `main`; GitHub release published. ZIP build script
  produces an installable `opensalestax.zip` artifact.
