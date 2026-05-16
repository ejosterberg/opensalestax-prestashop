# Contributing

Thanks for considering a contribution to **opensalestax-prestashop**.

## Ground rules

- **DCO sign-off required.** Every commit must be signed off
  with `git commit -s`. The Developer Certificate of Origin
  (DCO) is included by reference; see
  [`https://developercertificate.org/`](https://developercertificate.org/).
  The CI gate rejects unsigned commits.
- **No AI co-author trailers.** Don't add
  `Co-authored-by: Claude` or similar to commit messages. Per
  the umbrella program's constitution, this project credits its
  human maintainer only.
- **Apache-2.0 OR GPL-2.0-or-later, SPDX header.** Every source
  file starts with
  `// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later`
  (or the language equivalent for shell scripts /
  Twig / etc.). By contributing, you agree your contributions
  are licensed under both options.
- **Calculation-only.** This module does not file or remit tax.
  Pull requests adding filing / remittance / address-validation
  features will be closed without merge. See
  `specs/constitution.md` §5.
- **HTTP API is the contract.** Never call OpenSalesTax engine
  internals — go through the `ejosterberg/opensalestax` SDK. If
  the SDK is missing a method you need, open an issue there
  first.

## Branch model

- `main` is always release-quality. Tagged releases come off
  `main`.
- Feature branches: `feat/<slug>`. Bug fixes: `fix/<slug>`.
  Spec-only changes: `spec/<slug>`.
- Open a PR against `main`; CI runs PHPUnit, PHPStan,
  PHP-CS-Fixer, `composer audit`, and the DCO check. All must
  pass.

## Quality gate (must pass before merge)

```bash
composer install
composer check       # phpunit + phpstan + php-cs-fixer + composer audit
```

For more granular runs:

```bash
composer test           # PHPUnit
composer stan           # PHPStan level max
composer cs             # PHP-CS-Fixer dry-run
composer cs-fix         # PHP-CS-Fixer apply fixes
composer audit          # CVE check
```

If you touched `opensalestax.php` or anything packaged into the
ZIP, also run the build:

```bash
tools/build-zip.sh
```

## Specs are the source of truth

This repo follows the spec-kit convention. Before changing
anything non-trivial:

1. Read `specs/constitution.md` (non-negotiables).
2. Read `specs/current-state.md` (what's shipped).
3. Read `specs/handoff.md` (what the next session picks up).
4. Skim `specs/decisions/` (locked architectural decisions).

If your change touches ≥3 files OR adds a new API surface OR
spans more than one work session, write a phase directory under
`specs/phase-NN-<slug>/` with `spec.md` + `plan.md` +
`tasks.md` before opening the PR.
